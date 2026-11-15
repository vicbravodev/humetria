// The check from outside: does the site serve the landing the way the design says? It reads the document like a crawler
// that runs no JavaScript. Without dependencies: Node 20 or newer (fetch) is all it needs.
//
//   node scripts/synthetic/check-landing.mjs [--url https://humetria.com.mx/] [--expect-revision <commit>] [--wait 600] [--every 15]
//
// Exit 0 when everything holds, 1 with one line per problem on stderr. With --wait it tries again every --every seconds until
// it passes or the seconds run out (after a deploy the new version takes a moment to answer).
import { landingProblems } from '../ssr/landing-check.mjs';

const args = process.argv.slice(2);
const option = (name, fallback) => {
    const at = args.indexOf(`--${name}`);

    return at === -1 ? fallback : args[at + 1];
};

const decode = (text) =>
    text
        .replaceAll('&lt;', '<')
        .replaceAll('&gt;', '>')
        .replaceAll('&quot;', '"')
        .replaceAll('&#x27;', "'")
        .replaceAll('&#039;', "'")
        .replaceAll('&amp;', '&');

/** The attributes of every tag of this kind, in the document. */
const tagsOf = (html, tag) =>
    [...html.matchAll(new RegExp(`<${tag}\\b([^>]*)>`, 'g'))].map(([, rest]) =>
        Object.fromEntries(
            [...rest.matchAll(/\s([^\s=>/]+)(?:="([^"]*)")?/g)].map(
                ([, name, value]) => [name, decode(value ?? '')],
            ),
        ),
    );

const metaContent = (html, key, value) =>
    tagsOf(html, 'meta').find((attrs) => attrs[key] === value)?.content;

const headerProblems = (response) => {
    const header = (name) => response.headers.get(name);
    const problems = [];

    if (response.status !== 200) {
        problems.push(`expected 200, got ${response.status}`);
    }
    if (header('x-rendered-by') !== 'ssr') {
        problems.push(
            `X-Rendered-By is «${header('x-rendered-by')}», expected «ssr»`,
        );
    }
    if (header('x-robots-tag') !== null) {
        problems.push(
            `X-Robots-Tag is present («${header('x-robots-tag')}»): the landing must be indexable`,
        );
    }
    if (header('x-powered-by') !== null) {
        problems.push(`X-Powered-By is present («${header('x-powered-by')}»)`);
    }

    return problems;
};

const tagProblems = (html) => {
    const problems = [];

    if (
        !tagsOf(html, 'link').some(
            (link) => link.rel === 'canonical' && link.href,
        )
    ) {
        problems.push('no canonical link');
    }
    for (const property of [
        'og:title',
        'og:description',
        'og:image',
        'og:url',
    ]) {
        if (!metaContent(html, 'property', property)) {
            problems.push(`no ${property} meta`);
        }
    }

    const jsonLd = [
        ...html.matchAll(
            /<script[^>]*application\/ld\+json[^>]*>([\s\S]*?)<\/script>/g,
        ),
    ];

    try {
        if (
            jsonLd.length !== 1 ||
            typeof JSON.parse(jsonLd[0][1]) !== 'object'
        ) {
            throw new Error('not one object');
        }
    } catch {
        problems.push(
            `expected one JSON-LD with valid JSON, found ${jsonLd.length}`,
        );
    }

    return problems;
};

const revisionProblems = (html, expected) => {
    const build = metaContent(html, 'name', 'build-revision');
    const app = metaContent(html, 'name', 'app-revision');

    if (!build || !app) {
        return [
            `build-revision («${build}») and app-revision («${app}») must both be there`,
        ];
    }
    if (build !== app) {
        return [
            `build-revision («${build}») differs from app-revision («${app}»): Node and PHP are from different commits`,
        ];
    }

    return expected !== undefined && build !== expected
        ? [`the site runs revision «${build}», expected «${expected}»`]
        : [];
};

/** Everything wrong with one response, as a list of sentences. */
export const problemsOf = (response, html, expectedRevision) => [
    ...headerProblems(response),
    ...landingProblems(html),
    ...tagProblems(html),
    ...revisionProblems(html, expectedRevision),
];

async function checkOnce(url, expectedRevision) {
    const began = performance.now();

    try {
        const response = await fetch(url, {
            headers: { 'User-Agent': 'humetria-synthetic-check' },
        });
        const ttfb = Math.round(performance.now() - began);
        const html = await response.text();
        const problems = problemsOf(response, html, expectedRevision);

        if (problems.length === 0) {
            console.log(
                `ok: ${url} ssr, ttfb ${ttfb} ms, ${Buffer.byteLength(html)} bytes`,
            );
        }

        return problems;
    } catch (error) {
        return [`${url} did not answer: ${error.message}`];
    }
}

const url = option('url', 'https://humetria.com.mx/');
const expectedRevision = option('expect-revision');
const deadline = Date.now() + Number(option('wait', 0)) * 1000;
const pause = Number(option('every', 15)) * 1000;

for (;;) {
    const problems = await checkOnce(url, expectedRevision);

    if (problems.length === 0) {
        process.exit(0);
    }
    if (Date.now() + pause > deadline) {
        console.error(problems.join('\n'));
        process.exit(1);
    }

    console.error(
        `not yet (${problems.length} problems, the first: ${problems[0]}); trying again`,
    );
    await new Promise((resolve) => setTimeout(resolve, pause));
}
