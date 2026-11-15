import assert from 'node:assert/strict';
import { test } from 'node:test';
import { landingPage, render, seoGolden } from './ssr.mjs';

const decode = (text) =>
    text
        .replaceAll('&lt;', '<')
        .replaceAll('&gt;', '>')
        .replaceAll('&quot;', '"')
        .replaceAll('&#x27;', "'")
        .replaceAll('&amp;', '&');

/** `<meta name="a" content="b" data-inertia="c">` → { tag, attrs }, plus `text` when the tag has content. */
function parseTag(html) {
    const [, tag, rest, text] =
        /^<(\w+)((?:\s+[^\s=>]+(?:="[^"]*")?)*)\s*>(?:([\s\S]*)<\/\1>)?$/.exec(
            html,
        );
    const attrs = Object.fromEntries(
        [...rest.matchAll(/\s+([^\s=>]+)(?:="([^"]*)")?/g)].map((match) => [
            match[1],
            decode(match[2] ?? ''),
        ]),
    );

    return text === undefined
        ? { tag, attrs }
        : { tag, attrs, text: decode(text) };
}

// The same tags, with the same data-inertia keys, as the Blade fallback (tests/Feature/SeoFallbackSnapshotTest.php).
void test('the head Node renders matches the golden file tag by tag', async () => {
    const golden = seoGolden();
    const { json } = await render(landingPage());

    assert.deepEqual(json.head.map(parseTag), golden.tags);
});
