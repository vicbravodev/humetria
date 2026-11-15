import assert from 'node:assert/strict';
import { test } from 'node:test';
import { render } from './ssr.mjs';

void test('renders the landing in Node: 200 with a head and a body', async () => {
    const { status, json } = await render();

    assert.equal(status, 200);
    assert.ok(Array.isArray(json.head) && json.head.length > 0, 'head');
    assert.match(json.body, /data-server-rendered="true"/);
    assert.match(json.body, /<h1/);
});
