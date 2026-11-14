import assert from 'node:assert/strict';
import { test } from 'node:test';
import { render } from './ssr.mjs';

// A <video> picks its file by theme and by prefers-reduced-motion: the server cannot know either, and React does not patch
// attributes it hydrates. The films mount in the browser; the server prints their frame and their caption.
void test('the HTML Node renders has no <video>', async () => {
    const { json } = await render();

    assert.doesNotMatch(json.body, /<video\b/);
    assert.match(json.body, /<figure\b/);
});
