/*
 * What the service worker of a company's app decides, as pure functions. It is the only file the worker imports, and it
 * compiles in the page's project and in the worker's, so it uses neither DOM nor WebWorker types: only strings and URL.
 */

/** How long the network gets before the saved shell answers instead. */
export const NETWORK_TIMEOUT_MS = 4_000;

const ASSETS = '/build/assets/';
const CACHE_PREFIX = 'humetria-pwa-';

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

    if (url.pathname.startsWith(ASSETS)) {
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

/** Where the icon opens and where a tap on a notice goes. */
export function punchUrl(scope: string): string {
    return `${scope}/punch`;
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
