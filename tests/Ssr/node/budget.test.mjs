import assert from 'node:assert/strict';
import { test } from 'node:test';
import { render } from './ssr.mjs';

// The budget of the design (section 13 G): measured at 4 to 5 ms and 123 KB.
void test('200 renders in a row: p95 within 150 ms and the HTML within 250 KB', async () => {
    const times = [];
    let size = 0;

    for (let index = 0; index < 200; index++) {
        const start = performance.now();
        const { json } = await render();
        times.push(performance.now() - start);
        size = Math.max(size, Buffer.byteLength(json.body));
    }

    times.sort((a, b) => a - b);

    assert.ok(times[Math.ceil(times.length * 0.95) - 1] <= 150, 'p95');
    assert.ok(size <= 250 * 1024, `HTML of ${size} bytes`);
});
