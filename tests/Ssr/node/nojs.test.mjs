import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { baseUrl, withContext } from './browser.mjs';

const tools = readFileSync(
    new URL(
        '../../../resources/js/components/landing/landing-tools.ts',
        import.meta.url,
    ),
    'utf8',
);
const toolNames = [...tools.matchAll(/^ {8}name: '([^']+)'/gm)].map(
    ([, name]) => name,
);

// A reader that does not run JavaScript (a crawler, a text browser with styles, a blocked script) gets the page Node rendered:
// the entrances are CSS animations that end visible, not styles that hide the text until a script shows it.
void test('without JavaScript the headline and the main text are visible and the links are there', async () => {
    await withContext({ javaScriptEnabled: false }, async (context) => {
        const page = await context.newPage();
        await page.goto(baseUrl);

        // The entrance of the hero takes 1.25 s at most (0.55 s of delay and 0.7 s of animation).
        await page.waitForTimeout(2000);

        const opacities = await page.evaluate(() =>
            ['h1', '#hero-title ~ p', '#hero-title span'].map((selector) =>
                [...document.querySelectorAll(selector)].map(
                    (node) => getComputedStyle(node).opacity,
                ),
            ),
        );

        assert.equal(opacities[0].length, 1);
        assert.deepEqual(
            opacities.flat(),
            opacities.flat().map(() => '1'),
        );
        assert.equal(await page.locator('h1').count(), 1);
        assert.equal(await page.locator('title').count(), 1);
        assert.equal(await page.locator('main').count(), 1);
        assert.ok(
            (await page.locator('a[href="/privacy-notice"]').count()) > 0,
        );
        assert.ok(
            (await page.locator('a[href="#agenda-una-demo"]').count()) > 0,
        );

        const text = await page.evaluate(() => document.body.innerText);

        assert.equal(toolNames.length, 9);

        for (const name of toolNames) {
            assert.ok(text.includes(name), `${name} is readable`);
        }
    });
});

// The eight tool panels that are not chosen hide only when <html> has `js` (set by the inline script): without it the nine tools stack.
void test('without JavaScript the nine tool panels are visible, stacked, each with its name', async () => {
    await withContext({ javaScriptEnabled: false }, async (context) => {
        const page = await context.newPage();
        await page.goto(baseUrl);

        const panels = await page.evaluate(() =>
            [...document.querySelectorAll('[role=tabpanel]')].map((panel) => ({
                display: getComputedStyle(panel).display,
                height: panel.getBoundingClientRect().height,
                title: panel.querySelector('h3')?.textContent ?? '',
            })),
        );

        assert.equal(panels.length, 9);
        assert.ok(panels.every(({ display }) => display !== 'none'));
        assert.ok(panels.every(({ height }) => height > 0));
        assert.deepEqual(
            panels.map(({ title }) => title),
            toolNames,
        );
    });
});
