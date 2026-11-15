import { spawn } from 'node:child_process';
import { once } from 'node:events';
import { createServer } from 'node:net';

/** A port nobody listens on right now. */
export async function freePort() {
    const server = createServer().listen(0, '127.0.0.1');
    await once(server, 'listening');
    const { port } = server.address();
    server.close();
    await once(server, 'close');

    return port;
}

/** Runs a script of scripts/ssr with an environment; resolves with its exit code and what it wrote. */
export function run(script, env) {
    const child = spawn(process.execPath, [script], {
        env: { ...process.env, ...env },
    });
    let stderr = '';
    child.stderr.on('data', (chunk) => (stderr += chunk));
    child.stdout.resume();

    const done = once(child, 'exit').then(([code]) => ({ code, stderr }));

    return { child, done };
}

/** Whether something answers on the port. */
export const listening = (port) =>
    fetch(`http://127.0.0.1:${port}/health`).then(
        () => true,
        () => false,
    );

export const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
