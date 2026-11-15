import assert from 'node:assert/strict';
import { test } from 'node:test';
import { freePort, listening, run, sleep } from './helpers.mjs';

const supervisor = new URL(
    '../../../scripts/ssr/supervisor.mjs',
    import.meta.url,
).pathname;
const bundle = (name) =>
    new URL(`./fixtures/${name}.mjs`, import.meta.url).pathname;

// Every second of the design shrinks to a tenth: a probe every 100 ms, 500 ms to answer, three failures.
const start = async (name) => {
    const port = await freePort();

    return {
        port,
        ...run(supervisor, {
            SSR_BUNDLE: bundle(name),
            SSR_PORT: String(port),
            SUPERVISOR_INTERVAL_MS: '100',
            SUPERVISOR_TIMEOUT_MS: '500',
            SUPERVISOR_MAX_FAILURES: '3',
        }),
    };
};

void test('a good bundle keeps the supervisor alive, and SIGTERM stops it and its child', async () => {
    const { child, done, port } = await start('good');
    await sleep(1500);

    assert.equal(child.exitCode, null, 'still running after several probes');
    assert.equal(await listening(port), true, 'the bundle answers');

    child.kill('SIGTERM');
    const { code } = await done;

    assert.equal(code, 0);
    assert.equal(await listening(port), false, 'its child went with it');
});

void test('a bundle that throws on import makes the supervisor exit with a code that is not 0', async () => {
    const { done } = await start('throws');
    const { code } = await done;

    assert.notEqual(code, 0);
});

void test('a render that never answers makes it exit 1 after three failed probes, and takes the child down', async () => {
    const { done, port } = await start('hangs');
    const began = Date.now();
    const { code, stderr } = await done;

    assert.equal(code, 1);
    assert.match(stderr, /3/);
    assert.ok(Date.now() - began >= 1000, 'not before three probes had failed');
    assert.equal(await listening(port), false, 'the hung child was killed');
});
