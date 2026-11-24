import { describe, expect, it } from 'vitest';
import {
    NETWORK_TIMEOUT_MS,
    cacheName,
    fallsBackToShell,
    inScope,
    punchUrl,
    routeOf,
    shellAssets,
    shellUrl,
    staleCaches,
    workerSlug,
} from '@/lib/pwa/sw-rules';

const origin = 'https://app.test';
const scopePath = '/me/acme';

function route(
    url: string,
    overrides: { method?: string; mode?: string; origin?: string } = {},
) {
    return routeOf({
        method: 'GET',
        mode: 'navigate',
        origin,
        scopePath,
        url,
        ...overrides,
    });
}

describe('workerSlug', () => {
    it.each([
        ['/me/acme', 'acme'],
        ['/me/acme/punch', 'acme'],
        ['/me/acme/', 'acme'],
        ['/me/acme-industrial/week', 'acme-industrial'],
    ])('names the company of %s', (pathname, slug) => {
        expect(workerSlug(pathname)).toBe(slug);
    });

    it.each(['/me/', '/me', '/a/acme', '/admin', '/', '/mex/acme'])(
        'names no company for %s',
        (pathname) => {
            expect(workerSlug(pathname)).toBeNull();
        },
    );
});

describe('inScope', () => {
    it('takes the scope itself and what hangs from it, never a company that starts with its name', () => {
        expect(inScope('/me/acme', scopePath)).toBe(true);
        expect(inScope('/me/acme/punch', scopePath)).toBe(true);
        expect(inScope('/me/acme-industrial', scopePath)).toBe(false);
        expect(inScope('/me/acme-industrial/punch', scopePath)).toBe(false);
        expect(inScope('/me/other', scopePath)).toBe(false);
    });
});

describe('routeOf', () => {
    it('never touches what is not a GET', () => {
        expect(
            route(`${origin}/me/acme/punch/offline`, { method: 'POST' }),
        ).toBe('pass');
        expect(route(`${origin}/me/acme/punch`, { method: 'POST' })).toBe(
            'pass',
        );
    });

    it('never touches another origin', () => {
        expect(route('https://elsewhere.test/build/assets/app-x.js')).toBe(
            'pass',
        );
        expect(route('https://elsewhere.test/me/acme/punch')).toBe('pass');
    });

    it('caches the build files first', () => {
        expect(route(`${origin}/build/assets/app-x.js`, { mode: 'cors' })).toBe(
            'asset',
        );
        expect(
            route(`${origin}/build/assets/app-x.css`, { mode: 'no-cors' }),
        ).toBe('asset');
    });

    it('answers the pages of its own company by the network, with the shell as a fallback', () => {
        expect(route(`${origin}/me/acme`)).toBe('navigation');
        expect(route(`${origin}/me/acme/punch`)).toBe('navigation');
        expect(route(`${origin}/me/acme/punch?q=1.abc`)).toBe('navigation');
    });

    it.each([
        `${origin}/me/acme-industrial/punch`,
        `${origin}/me/other/punch`,
        `${origin}/admin`,
        `${origin}/`,
    ])('leaves %s to the network', (url) => {
        expect(route(url)).toBe('pass');
    });

    it.each([
        `${origin}/me/acme/sw.js`,
        `${origin}/me/acme/manifest.webmanifest`,
        `${origin}/me/acme/tickets`,
    ])('leaves the technical %s to the network', (url) => {
        expect(route(url, { mode: 'cors' })).toBe('pass');
    });

    it('leaves what Inertia fetches to the network: only a navigation is a page', () => {
        expect(route(`${origin}/me/acme/week`, { mode: 'cors' })).toBe('pass');
    });
});

describe('fallsBackToShell', () => {
    it.each([404, 419, 500, 503])(
        'never for a %i: the server answered',
        (status) => {
            expect(fallsBackToShell(status)).toBe(false);
        },
    );

    it('only without a network or after the wait', () => {
        expect(fallsBackToShell('network-error')).toBe(true);
        expect(fallsBackToShell('timeout')).toBe(true);
    });

    it('waits four seconds', () => {
        expect(NETWORK_TIMEOUT_MS).toBe(4_000);
    });
});

describe('addresses of the app', () => {
    it('puts the shell and Marcar under the scope', () => {
        expect(shellUrl('https://h/me/acme')).toBe('https://h/me/acme/offline');
        expect(punchUrl('https://h/me/acme')).toBe('https://h/me/acme/punch');
    });
});

describe('shellAssets', () => {
    const html = `<!DOCTYPE html><html><head>
        <link rel="icon" href="/favicon.ico">
        <link rel="stylesheet" crossorigin href="/build/assets/app-abc.css">
        <script type="module" crossorigin src="/build/assets/offline-def.js"></script>
        <link rel="modulepreload" href="/build/assets/chunk-ghi.js">
        <link rel="modulepreload" href="https://elsewhere.test/build/assets/other.js">
        <link rel="manifest" href="/me/acme/manifest.webmanifest">
        <script src="/build/assets/offline-def.js" type="module"></script>
        <script src="/favicon.js"></script>
        </head><body><div id="offline-app"></div></body></html>`;

    it('lists the files of the build the shell needs, once each', () => {
        expect(shellAssets(html, origin)).toEqual([
            '/build/assets/app-abc.css',
            '/build/assets/offline-def.js',
            '/build/assets/chunk-ghi.js',
        ]);
    });

    it('finds nothing in a page that carries no build file', () => {
        expect(shellAssets('<html></html>', origin)).toEqual([]);
    });
});

describe('caches', () => {
    it('names the cache of a company and a revision', () => {
        expect(cacheName('acme', 'abc123')).toBe('humetria-pwa-acme-abc123');
    });

    it('drops only the old caches of its own company', () => {
        const keys = [
            'humetria-pwa-acme-old111',
            'humetria-pwa-acme-abc123',
            'humetria-pwa-acme-industrial-old111',
            'humetria-pwa-other-old111',
            'something-else',
        ];

        expect(staleCaches(keys, 'acme', 'abc123')).toEqual([
            'humetria-pwa-acme-old111',
        ]);
        expect(staleCaches(keys, 'acme-industrial', 'old111')).toEqual([]);
    });
});
