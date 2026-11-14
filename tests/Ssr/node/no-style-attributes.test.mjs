import assert from 'node:assert/strict';
import { test } from 'node:test';
import { render } from './ssr.mjs';

// The CSP has no style-src-attr: a style="" in the HTML is blocked by the browser (a violation) and, written by motion's
// `initial`, hid the text until the script ran. The entrances are CSS now, so the server prints no style at all.
void test('the HTML Node renders has no style attribute inside #app', async () => {
    const { json } = await render();
    const styled = json.body.match(/<[^>]+\sstyle="[^"]*"/g) ?? [];

    assert.deepEqual(styled, []);
});
