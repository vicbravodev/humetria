import { createRequire } from 'node:module';
import fs from 'node:fs';
import path from 'node:path';
const require = createRequire('/Users/victorjesusbravodelapena/code/delapengineering.com/humetria/package.json');
const { chromium } = require('playwright');
const ROOT = '/private/tmp/claude-501/-Users-victorjesusbravodelapena--claude-spares-spare-nFyuKU/d60ee1f5-bf5f-4d18-b2ce-ca51a448c069/scratchpad/render2';
const [,, rel, out, w = '1440', theme = ''] = process.argv;
const mime = { '.html': 'text/html', '.js': 'text/javascript', '.css': 'text/css', '.json': 'application/json' };
const b = await chromium.launch();
const ctx = await b.newContext({ viewport: { width: +w, height: 900 } });
// Serve the canvas files from disk under https://render.local/ (no port opened).
await ctx.route('https://render.local/**', (route) => {
  const p = new URL(route.request().url()).pathname;
  const f = path.join(ROOT, decodeURIComponent(p));
  if (fs.existsSync(f) && fs.statSync(f).isFile()) {
    route.fulfill({ status: 200, contentType: mime[path.extname(f)] || 'application/octet-stream', body: fs.readFileSync(f) });
  } else route.fulfill({ status: 404, body: 'nf' });
});
const p = await ctx.newPage();
const logs = [];
p.on('console', (m) => { if (['error', 'warning'].includes(m.type())) logs.push(m.type() + ': ' + m.text().slice(0, 220)); });
p.on('pageerror', (e) => logs.push('pageerror: ' + e.message.slice(0, 220)));
p.on('requestfailed', (r) => logs.push('reqfail: ' + r.url().slice(0, 100)));
await p.goto('https://render.local/' + rel, { waitUntil: 'networkidle', timeout: 45000 }).catch((e) => logs.push('goto: ' + e.message.slice(0, 200)));
await p.waitForTimeout(2500);
if (theme) {
  await p.evaluate((t) => { document.querySelectorAll('.t-light,.t-dark').forEach((e) => { e.classList.remove('t-light', 't-dark'); e.classList.add(t); }); }, theme);
  await p.waitForTimeout(500);
}
const h = await p.evaluate(() => document.documentElement.scrollHeight);
await p.screenshot({ path: out, fullPage: true });
const text = (await p.evaluate(() => document.body.innerText)).replace(/\s+/g, ' ').slice(0, 200);
console.log(JSON.stringify({ h, text, logs: logs.slice(0, 8) }));
await b.close();
