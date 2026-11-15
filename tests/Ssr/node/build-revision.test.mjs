import assert from 'node:assert/strict';
import { spawn, spawnSync } from 'node:child_process';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { test } from 'node:test';
import { freePort, sleep } from './helpers.mjs';
import { landingPage, render } from './ssr.mjs';

const root = new URL('../../../', import.meta.url).pathname;
const preload = new URL('./fixtures/other-port.mjs', import.meta.url).pathname;

const revisionTag = (head) =>
    head.find((tag) => tag.includes('name="build-revision"'));

void test('a build with VITE_BUILD_REVISION tells the revision in its head, and one without it prints no tag', async () => {
    const { json } = await render();

    assert.equal(
        revisionTag(json.head),
        undefined,
        'the local build has no revision',
    );

    const out = mkdtempSync(join(tmpdir(), 'ssr-revision-'));
    const port = await freePort();
    let node;

    try {
        const build = spawnSync(
            'npx',
            ['vp', 'build', '--ssr', '--outDir', out],
            {
                cwd: root,
                env: { ...process.env, VITE_BUILD_REVISION: 'f00ba4c0ffee' },
            },
        );
        assert.equal(build.status, 0, String(build.stderr));

        node = spawn(
            process.execPath,
            ['--import', preload, join(out, 'app.js')],
            {
                env: { ...process.env, SSR_PORT: String(port) },
                stdio: 'ignore',
            },
        );

        let answer;
        for (let tries = 0; tries < 50 && answer === undefined; tries++) {
            answer = await fetch(`http://127.0.0.1:${port}/render`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(landingPage()),
            }).then(
                (response) => response.json(),
                () => undefined,
            );
            if (answer === undefined) {
                await sleep(100);
            }
        }

        assert.match(
            revisionTag(answer.head),
            /^<meta [^>]*content="f00ba4c0ffee"[^>]*data-inertia="build-revision"|^<meta [^>]*data-inertia="build-revision"[^>]*content="f00ba4c0ffee"/,
        );
    } finally {
        node?.kill('SIGKILL');
        rmSync(out, { recursive: true, force: true });
    }
});
