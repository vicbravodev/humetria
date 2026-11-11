// Revisión de desbordes ANTES de renderizar. Mide el DOM de fotogramas asentados de cada capítulo (80 % y 92 %, ya sin animaciones a medias).
// Uso: node scripts/qa-layout.mjs <Composición> <id-de-presentación> [tema]
//   p. ej.: node scripts/qa-layout.mjs OrgChart org-chart dark
// Sale con código 1 si encuentra algún [layout]; no escribe videos ni toca public/.
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';

const [comp, id, theme = 'dark'] = process.argv.slice(2);
if (!comp || !id) throw new Error('Uso: qa-layout.mjs <Composición> <id> [dark|light]');
const pacing = JSON.parse(readFileSync('src/kit/pacing.json', 'utf8'));
const script = JSON.parse(readFileSync(`src/presentations/${id}/script.json`, 'utf8'));
const manifest = JSON.parse(readFileSync(`voice/manifest.${id}.angie.json`, 'utf8'));

let start = 0;
const frames = [];
script.lines.forEach((line, i) => {
    const take = manifest.lines.find((m) => m.id === line.id);
    const last = i === script.lines.length - 1;
    const len = pacing.lead + Math.ceil(take.durationSec * pacing.fps) + (last && !script.outro ? script.hold ?? pacing.outro : pacing.tail);
    frames.push([`cap ${i + 1} (80 %)`, start + Math.round(len * 0.8)], [`cap ${i + 1} (92 %)`, start + Math.round(len * 0.92)]);
    start += len;
});

let bad = 0;
for (const [label, frame] of frames) {
    let out = '';
    let ok = true;
    try {
        out = execFileSync('npx', ['remotion', 'still', 'src/index.ts', comp, 'out/qa.png', `--frame=${frame}`, `--props={"theme":"${theme}","probe":true}`], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
    } catch (e) {
        ok = false;
        out = `${e.stdout ?? ''}${e.stderr ?? ''}`;
    }
    // Sin desbordes, el fotograma se renderiza y el comando termina bien; con desbordes, la sonda cancela con un error [layout].
    const issues = [...out.matchAll(/((?:ANCHO|ALTO|LIENZO): [^\n]*)/g)].map((m) => m[1]);
    const ran = ok || out.includes('[layout]');
    if (!ran) {
        console.log(`✗ ${label} (fotograma ${frame}): la sonda no corrió`);
        bad++;
    } else if (issues.length) {
        console.log(`✗ ${label} (fotograma ${frame})`);
        issues.forEach((i) => console.log(`    ${i}`));
        bad += issues.length;
    } else {
        console.log(`✓ ${label} (fotograma ${frame})`);
    }
}
console.log(bad === 0 ? `${comp}: sin desbordes` : `${comp}: ${bad} problema(s)`);
process.exit(bad === 0 ? 0 : 1);
