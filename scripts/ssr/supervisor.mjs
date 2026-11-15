// The ssr image's PID 1 child: Docker does not restart a container that is only "unhealthy", so this does. It runs the
// bundle as a child, repeats the healthcheck's render every SUPERVISOR_INTERVAL_MS and, after SUPERVISOR_MAX_FAILURES
// failures in a row, kills the child and exits 1: `restart: unless-stopped` starts the container again. It also exits when
// the child does. The env vars are for the tests; the defaults are the production values.
import { spawn } from 'node:child_process';
import { renderProblems } from './landing-check.mjs';

const port = process.env.SSR_PORT ?? '13714';
const interval = Number(process.env.SUPERVISOR_INTERVAL_MS ?? 30_000);
const timeoutMs = Number(process.env.SUPERVISOR_TIMEOUT_MS ?? 2000);
const maxFailures = Number(process.env.SUPERVISOR_MAX_FAILURES ?? 3);

const child = spawn(
    process.execPath,
    [process.env.SSR_BUNDLE ?? 'bootstrap/ssr/app.js'],
    {
        stdio: 'inherit',
    },
);

const leave = (code) => {
    child.kill('SIGKILL');
    process.exit(code);
};

child.on('exit', (code, signal) => {
    console.error(
        `ssr bundle exited (${signal ?? code}): exiting so Docker restarts the container`,
    );
    leave(code === 0 || code === null ? 1 : code);
});
process.on('SIGTERM', () => leave(0));
process.on('SIGINT', () => leave(0));

// One probe at a time: a render that hangs must count once, not once per tick.
let failures = 0;
for (;;) {
    await new Promise((resolve) => setTimeout(resolve, interval));

    const problems = await renderProblems({
        url: `http://127.0.0.1:${port}`,
        timeoutMs,
    });

    failures = problems.length > 0 ? failures + 1 : 0;

    if (problems.length > 0) {
        console.error(
            `ssr probe failed (${failures} of ${maxFailures}): ${problems.join('; ')}`,
        );
    }

    if (failures >= maxFailures) {
        console.error(
            `ssr probe failed ${maxFailures} times in a row: exiting so Docker restarts the container`,
        );
        leave(1);
    }
}
