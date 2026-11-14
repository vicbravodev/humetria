import assert from 'node:assert/strict';
import { test } from 'node:test';
import { baseUrl, withContext } from './browser.mjs';

// The HTML Node rendered shows the form before the script arrives; someone on a slow network starts typing right away.
// React must hydrate over what they wrote instead of resetting it.
void test('what is typed in the demo form before React hydrates is still there after', async () => {
    await withContext({}, async (context) => {
        const page = await context.newPage();
        const held = [];
        let holding = true;

        // The page's scripts are held (not dropped) until the person has typed; the ones that come later pass.
        await page.route(/\/build\/assets\/.*\.js(\?.*)?$/, (route) => {
            if (holding) {
                held.push(route);
            } else {
                void route.continue();
            }
        });
        // Module scripts hold DOMContentLoaded until they run: the page is up once the response is in and the form is there.
        await page.goto(baseUrl, { waitUntil: 'commit' });
        await page.waitForSelector('#demo-name');

        await page.fill('#demo-name', 'Ana Pérez');
        await page.fill('#demo-email', 'ana@empresa.mx');
        assert.ok(held.length > 0, 'the scripts were held');

        holding = false;
        await Promise.all(held.map((route) => route.continue()));

        // The films mount only in the browser: a <video> shows that React hydrated.
        await page.waitForSelector('video', { timeout: 15000 });

        assert.equal(await page.inputValue('#demo-name'), 'Ana Pérez');
        assert.equal(await page.inputValue('#demo-email'), 'ana@empresa.mx');
    });
});
