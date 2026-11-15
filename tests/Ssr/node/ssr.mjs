import { readFileSync } from 'node:fs';

const root = new URL('../../../', import.meta.url);

export const ssrUrl = process.env.SSR_URL ?? 'http://127.0.0.1:13714';

export const landingPage = () =>
    JSON.parse(
        readFileSync(new URL('scripts/ssr/fixtures/landing-page.json', root)),
    );

export const seoGolden = () =>
    JSON.parse(readFileSync(new URL('tests/Ssr/fixtures/seo-tags.json', root)));

/** POST /render, the way Inertia's PHP client does: the page object as JSON. */
export async function render(page = landingPage()) {
    const response = await fetch(`${ssrUrl}/render`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(page),
    });

    return { status: response.status, json: await response.json() };
}
