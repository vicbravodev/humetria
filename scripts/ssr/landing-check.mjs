// What «the landing rendered» means, in one place: the healthcheck and the supervisor of the ssr image ask it of Node's
// /render, the synthetic check (scripts/synthetic) asks it of the document the site serves. Without dependencies.
import { readFileSync } from 'node:fs';

// The same relative path in the repository and in the ssr image (/srv/ssr/tests/Ssr/fixtures): one list, never a copy.
const expected = JSON.parse(
    readFileSync(
        new URL(
            '../../tests/Ssr/fixtures/landing-expected.json',
            import.meta.url,
        ),
    ),
);
const page = readFileSync(
    new URL('./fixtures/landing-page.json', import.meta.url),
    'utf8',
);

/** The text a reader gets: no script, noscript, meta or style, no tags, one space between words. */
export const textOf = (html) =>
    html
        .replace(/<(script|noscript|style)\b[\s\S]*?<\/\1>/g, ' ')
        .replace(/<meta\b[^>]*>/g, ' ')
        .replace(/<[^>]+>/g, ' ')
        .replace(/[\s ]+/g, ' ');

/** What is missing from the HTML of the landing: one h1 with the expected text and the nine tools by name. */
export function landingProblems(html) {
    const problems = [];
    const h1s = html.match(/<h1[\s>][\s\S]*?<\/h1>/g) ?? [];

    if (h1s.length !== 1) {
        problems.push(`expected one h1, found ${h1s.length}`);
    } else if (textOf(h1s[0]).trim() !== expected.h1) {
        problems.push(`the h1 does not say «${expected.h1}»`);
    }

    const text = textOf(html);

    for (const { name } of expected.tools) {
        if (!text.includes(name)) {
            problems.push(`the tool «${name}» is not in the content`);
        }
    }

    return problems;
}

/** Posts the fixture page to Node's /render and says what is wrong with the answer (nothing: an empty list). */
export async function renderProblems({ url, timeoutMs }) {
    try {
        const response = await fetch(`${url}/render`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: page,
            signal: AbortSignal.timeout(timeoutMs),
        });

        if (!response.ok) {
            return [`/render answered ${response.status}`];
        }

        const { body } = await response.json();

        return typeof body === 'string'
            ? landingProblems(body)
            : ['/render answered without a body'];
    } catch (error) {
        return [
            error.name === 'TimeoutError'
                ? `timeout: /render did not answer in ${timeoutMs} ms`
                : `/render failed: ${error.message}`,
        ];
    }
}
