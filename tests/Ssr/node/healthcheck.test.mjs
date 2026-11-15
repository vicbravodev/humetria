import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { test } from 'node:test';
import { freePort, run } from './helpers.mjs';
import { ssrUrl } from './ssr.mjs';

const healthcheck = new URL(
    '../../../scripts/ssr/healthcheck.mjs',
    import.meta.url,
).pathname;

/** A server that answers every POST /render with this body (or never, when it is null). */
async function fakeRender(body) {
    const port = await freePort();
    const server = createServer((request, response) => {
        request.resume();
        if (body !== null) {
            response.setHeader('Content-Type', 'application/json');
            response.end(JSON.stringify({ head: [], body }));
        }
    }).listen(port, '127.0.0.1');

    return {
        url: `http://127.0.0.1:${port}`,
        close: () => {
            server.closeAllConnections();
            server.close();
        },
    };
}

void test('exits 0 when the real bundle renders the landing', async () => {
    const { code, stderr } = await run(healthcheck, { SSR_URL: ssrUrl }).done;

    assert.equal(code, 0, stderr);
});

void test('exits 1 when the render has no h1', async () => {
    const server = await fakeRender('<div id="app"><p>no title</p></div>');
    const { code, stderr } = await run(healthcheck, { SSR_URL: server.url })
        .done;
    server.close();

    assert.equal(code, 1);
    assert.match(stderr, /h1/);
});

void test('exits 1 when nobody answers', async () => {
    const port = await freePort();
    const { code } = await run(healthcheck, {
        SSR_URL: `http://127.0.0.1:${port}`,
    }).done;

    assert.equal(code, 1);
});

void test('exits 1 when the render takes longer than the limit', async () => {
    const server = await fakeRender(null);
    const { code, stderr } = await run(healthcheck, {
        SSR_URL: server.url,
        SSR_TIMEOUT_MS: '300',
    }).done;
    server.close();

    assert.equal(code, 1);
    assert.match(stderr, /timeout|aborted/i);
});
