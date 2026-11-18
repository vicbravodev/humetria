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
        assert.ok((await page.locator('a[href="/blog"]').count()) > 0);
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

// Each tool is a card that links to its own page: without JavaScript the nine are there, visible, each with its name.
void test('without JavaScript the nine tool cards are visible, each with its name and its page', async () => {
    await withContext({ javaScriptEnabled: false }, async (context) => {
        const page = await context.newPage();
        await page.goto(baseUrl);

        const cards = await page.evaluate(() =>
            [...document.querySelectorAll('[id^=herramienta-]')].map(
                (card) => ({
                    display: getComputedStyle(card).display,
                    height: card.getBoundingClientRect().height,
                    text: card.textContent ?? '',
                    href: card.getAttribute('href') ?? '',
                }),
            ),
        );

        assert.equal(cards.length, 9);
        assert.ok(cards.every(({ display }) => display !== 'none'));
        assert.ok(cards.every(({ height }) => height > 0));
        assert.ok(
            cards.every(({ href }) => href.startsWith('/') && href.length > 1),
        );
        for (const name of toolNames) {
            assert.ok(
                cards.some(({ text }) => text.includes(name)),
                `${name} has a card`,
            );
        }
    });
});
