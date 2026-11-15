import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { render } from './ssr.mjs';

const expected = JSON.parse(
    readFileSync(new URL('../fixtures/landing-expected.json', import.meta.url)),
);

/** The text a reader gets: no script, noscript, meta or style, no tags, one space between words. */
const textOf = (html) =>
    html
        .replace(/<(script|noscript|style)\b[\s\S]*?<\/\1>/g, ' ')
        .replace(/<meta\b[^>]*>/g, ' ')
        .replace(/<[^>]+>/g, ' ')
        .replace(/[\s ]+/g, ' ');

void test('the HTML Node renders has one h1, a server marker and the nine tools as tab panels', async () => {
    const { json } = await render();

    assert.match(json.body, /data-server-rendered="true"/);
    assert.equal((json.body.match(/<h1[\s>]/g) ?? []).length, 1);
    assert.equal(
        textOf(json.body.match(/<h1[\s\S]*?<\/h1>/)[0]).trim(),
        expected.h1,
    );
    assert.equal(expected.tools.length, 9);
    assert.equal((json.body.match(/role="tabpanel"/g) ?? []).length, 9);
});

/** The text of each tab panel, by the id it has, up to where the next panel starts. */
const panelsOf = (html) => {
    const starts = [...html.matchAll(/<div[^>]*role="tabpanel"[^>]*>/g)];

    return new Map(
        starts.map((open, index) => [
            open[0].match(/id="([^"]+)"/)?.[1],
            textOf(html.slice(open.index, starts[index + 1]?.index)),
        ]),
    );
};

void test('each tool has its own panel with its name and its description in the content', async () => {
    const { json } = await render();
    const panels = panelsOf(json.body);

    for (const { key, name, hint } of expected.tools) {
        const panel = panels.get(`tool-panel-${key}`);

        assert.ok(panel !== undefined, `${key} has a panel`);
        assert.ok(panel.includes(name), `${key}: name in its panel`);
        assert.ok(panel.includes(hint), `${key}: description in its panel`);
    }
});
