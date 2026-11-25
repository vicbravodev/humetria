/*
 * What the service worker of a company's app decides, as pure functions. It is the only file the worker imports, and it
 * compiles in the page's project and in the worker's, so it uses neither DOM nor WebWorker types: only strings and URL.
 */

/** How long the network gets before the saved shell answers instead. */
export const NETWORK_TIMEOUT_MS = 4_000;

const ASSETS = '/build/assets/';
/** The images of the brand (the «hecho con Humetría» seal at the foot of the pages): kept too, or the shell shows a broken picture. */
const BRAND = '/brand/';
const CACHE_PREFIX = 'humetria-pwa-';

/**
 * The seals the shell draws at its foot («hecho con Humetría», light and dark): images the HTML of the shell does not name
 * (React draws them), so they are kept at install with the rest, not left to a second visit.
 */
export const SHELL_IMAGES = [
    '/brand/humetria-sello-hecho-con-gris.svg',
    '/brand/humetria-sello-hecho-con-blanco.svg',
];

/** The `{slug}` of `/me/{slug}` and of what hangs from it; null for any other address. */
export function workerSlug(pathname: string): string | null {
    return /^\/me\/([^/]+)(?:\/|$)/.exec(pathname)?.[1] ?? null;
}

/** The scope itself or what hangs from it: `/me/acme` is in, `/me/acme-industrial` is not. */
export function inScope(pathname: string, scopePath: string): boolean {
    return pathname === scopePath || pathname.startsWith(`${scopePath}/`);
}

export type Route = 'asset' | 'navigation' | 'pass';

/**
 * What the worker does with a request: `asset` (a file of the build: the cache first), `navigation` (a page of its own
 * company: the network, and the shell only if the network is down) or `pass` (not its business: the browser goes on).
 */
export function routeOf(request: {
    method: string;
    url: string;
    mode: string;
    origin: string;
    scopePath: string;
}): Route {
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== request.origin) {
        return 'pass';
    }

    if (url.pathname.startsWith(ASSETS) || url.pathname.startsWith(BRAND)) {
        return 'asset';
    }

    return request.mode === 'navigate' &&
        inScope(url.pathname, request.scopePath)
        ? 'navigation'
        : 'pass';
}

/** The shell answers only when there is no network or it took too long: never in front of a 4xx or 5xx. */
export function fallsBackToShell(
    outcome: 'network-error' | 'timeout' | number,
): boolean {
    return typeof outcome === 'string';
}

export function shellUrl(scope: string): string {
    return `${scope}/offline`;
}

/**
 * The notice every push shows. The push arrives with no body and the text is fixed here: nothing of the person, the company or
 * the mark can reach the lock screen (and iOS revokes a subscription whose push shows nothing, so every push shows this).
 */
export const NOTICE = {
    title: 'Humetría',
    body: 'Tiene un aviso en Humetría',
} as const;

/** Where the icon opens and where a tap on a notice goes. */
export function punchUrl(scope: string): string {
    return `${scope}/punch`;
}

/** The index of the first open window that is in the company's app (a tap on a notice focuses it); -1 when there is none. */
export function windowInScope(urls: string[], scopePath: string): number {
    return urls.findIndex((url) => inScope(new URL(url).pathname, scopePath));
}

/** The files of the build the shell loads: its module scripts, stylesheets and preloads, from this origin. */
export function shellAssets(html: string, origin: string): string[] {
    const found = [...html.matchAll(/<(script|link)\b[^>]*>/gi)].flatMap(
        ([tag]) => {
            const source = /\b(?:src|href)="([^"]+)"/i.exec(tag)?.[1];
            const wanted =
                /\b(?:type="module"|rel="(?:stylesheet|modulepreload)")/i.test(
                    tag,
                );

            return source !== undefined && wanted ? [source] : [];
        },
    );

    return [
        ...new Set(
            found
                .map((source) => new URL(source, origin))
                .filter(
                    (url) =>
                        url.origin === origin &&
                        url.pathname.startsWith(ASSETS),
                )
                .map((url) => url.pathname),
        ),
    ];
}

export function cacheName(slug: string, revision: string): string {
    return `${CACHE_PREFIX}${slug}-${revision}`;
}

/** The old caches of this company: `acme` never touches those of `acme-industrial` (a revision has no hyphen). */
export function staleCaches(
    keys: string[],
    slug: string,
    revision: string,
): string[] {
    const own = `${CACHE_PREFIX}${slug}-`;

    return keys.filter(
        (key) =>
            key.startsWith(own) &&
            !key.slice(own.length).includes('-') &&
            key !== cacheName(slug, revision),
    );
}
