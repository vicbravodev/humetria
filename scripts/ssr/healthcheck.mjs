// The healthcheck of the ssr container: can Node render the landing? An open port is not enough (see the design, 7.4).
// Exit 0 when POST /render answers in time with the h1 and the nine tools, 1 otherwise, saying why on stderr.
import { renderProblems } from './landing-check.mjs';

const problems = await renderProblems({
    url: process.env.SSR_URL ?? 'http://127.0.0.1:13714',
    timeoutMs: Number(process.env.SSR_TIMEOUT_MS ?? 2000),
});

if (problems.length > 0) {
    console.error(problems.join('\n'));
}

process.exit(problems.length > 0 ? 1 : 0);
