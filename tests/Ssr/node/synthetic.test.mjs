import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { test } from 'node:test';
import { baseUrl } from './browser.mjs';
import { freePort, run } from './helpers.mjs';

const check = new URL(
    '../../../scripts/synthetic/check-landing.mjs',
    import.meta.url,
).pathname;

/** The document the site serves today, the one the check is written for. */
const live = await fetch(baseUrl).then((response) => response.text());

// What production serves once the revision goes through: the same document with the meta Node's bundle adds.
const good = live.replace(
    '</head>',
    '<meta name="build-revision" content="unknown"></head>',
);
const goodHeaders = { 'content-type': 'text/html', 'x-rendered-by': 'ssr' };

/** A site that answers with these responses in order, the last one from then on. */
async function site(...responses) {
    const port = await freePort();
    let served = 0;
    const server = createServer((request, response) => {
        request.resume();
        const {
            status = 200,
            headers = goodHeaders,
            html = good,
        } = responses[Math.min(served++, responses.length - 1)];
        response.writeHead(status, headers);
        response.end(html);
    }).listen(port, '127.0.0.1');

    return { url: `http://127.0.0.1:${port}/`, close: () => server.close() };
}

async function checkSite(responses, args = []) {
    const server = await site(...responses);
    const result = await run(check, {}, ['--url', server.url, ...args]).done;
    server.close();

    return result;
}

void test('the document with Node, the meta of the revision and the right headers passes', async () => {
    const { code, stderr } = await checkSite([{}]);

    assert.equal(code, 0, stderr);
});

// A local server is not production: it sends noindex (APP_ENV is not production), PHP's own X-Powered-By, and its build has no
// revision. Everything else the check asks of the document, the real render already has.
void test('the real server fails only for what is production-only: noindex, X-Powered-By and the revision', async () => {
    const { code, stderr } = await run(check, {}, ['--url', baseUrl]).done;
    const lines = stderr.trim().split('\n');

    assert.equal(code, 1);
    assert.equal(lines.length, 3, stderr);
    assert.ok(lines.some((line) => /build-revision/.test(line)));
    assert.ok(lines.some((line) => /X-Robots-Tag/.test(line)));
    assert.ok(lines.some((line) => /X-Powered-By/.test(line)));
});

const broken = {
    'rendered by csr': [
        { headers: { ...goodHeaders, 'x-rendered-by': 'csr' } },
        /X-Rendered-By/,
    ],
    'no X-Rendered-By': [
        { headers: { 'content-type': 'text/html' } },
        /X-Rendered-By/,
    ],
    'a 503': [{ status: 503 }, /503/],
    noindex: [
        { headers: { ...goodHeaders, 'x-robots-tag': 'noindex, nofollow' } },
        /X-Robots-Tag/,
    ],
    'X-Powered-By': [
        { headers: { ...goodHeaders, 'x-powered-by': 'PHP/8.5' } },
        /X-Powered-By/,
    ],
    'no h1': [{ html: good.replace(/<h1[\s\S]*?<\/h1>/, '') }, /h1/],
    'a tool missing': [
        { html: good.replaceAll('Ley Silla', 'Otra') },
        /Ley Silla/,
    ],
    'no canonical': [
        { html: good.replace(/<link[^>]*rel="canonical"[^>]*>/, '') },
        /canonical/,
    ],
    'no og:title': [
        { html: good.replace(/<meta[^>]*property="og:title"[^>]*>/, '') },
        /og:title/,
    ],
    'no JSON-LD': [
        {
            html: good.replace(
                /<script[^>]*application\/ld\+json[\s\S]*?<\/script>/,
                '',
            ),
        },
        /JSON-LD/,
    ],
    'no build-revision': [{ html: live }, /build-revision/],
    'revisions that differ': [
        {
            html: good.replace(
                'content="unknown"></head>',
                'content="abc123"></head>',
            ),
        },
        /abc123/,
    ],
};

for (const [name, [response, message]] of Object.entries(broken)) {
    void test(`fails with ${name}`, async () => {
        const { code, stderr } = await checkSite([response]);

        assert.equal(code, 1);
        assert.match(stderr, message);
    });
}

void test('--expect-revision compares both metas with the commit that was deployed', async () => {
    assert.equal(
        (await checkSite([{}], ['--expect-revision', 'unknown'])).code,
        0,
    );

    const { code, stderr } = await checkSite(
        [{}],
        ['--expect-revision', 'f00ba4'],
    );

    assert.equal(code, 1);
    assert.match(stderr, /f00ba4/);
});

void test('--wait retries until the new version answers', async () => {
    const csr = { headers: { ...goodHeaders, 'x-rendered-by': 'csr' } };

    assert.equal(
        (await checkSite([csr, csr, {}], ['--wait', '5', '--every', '0.1']))
            .code,
        0,
    );
    assert.equal(
        (await checkSite([csr], ['--wait', '0.5', '--every', '0.1'])).code,
        1,
    );
});
