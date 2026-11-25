import {
    NETWORK_TIMEOUT_MS,
    SHELL_IMAGES,
    cacheName,
    fallsBackToShell,
    noticeOfPush,
    noticeTarget,
    punchUrl,
    routeOf,
    shellAssets,
    shellUrl,
    staleCaches,
    windowInScope,
    workerSlug,
} from '@/lib/pwa/sw-rules';

/*
 * The service worker of a company's app (`/me/{slug}/sw.js`, scope `/me/{slug}`). It keeps only the files of the build and
 * the shell (`/me/{slug}/offline`): never the HTML of a page, never data of a person, never a request that is not a GET.
 * A page is always asked of the network first; the shell answers only when there is no network or it took too long.
 * A push carries only its kind, encrypted, and shows the fixed text of that kind; a tap on it opens Marcar (see the notices in sw-rules).
 * It imports nothing but sw-rules: it is served as one classic file (see vite.config.ts).
 */
declare const self: ServiceWorkerGlobalScope;

const revision = new URL(self.location.href).searchParams.get('v') ?? 'unknown';
const scope = self.registration.scope.replace(/\/$/, '');
const scopePath = new URL(scope).pathname;
const slug = workerSlug(scopePath) ?? 'unknown';
const cacheKey = cacheName(slug, revision);

async function saveShell(): Promise<void> {
    const cache = await caches.open(cacheKey);
    const response = await fetch(shellUrl(scope), { cache: 'no-store' });

    if (!response.ok) {
        throw new Error('The shell is not available.');
    }

    const html = await response.clone().text();
    await cache.put(shellUrl(scope), response);
    await cache.addAll([
        ...shellAssets(html, self.location.origin),
        ...SHELL_IMAGES,
    ]);
}

async function dropOldCaches(): Promise<void> {
    const keys = await caches.keys();
    await Promise.all(
        staleCaches(keys, slug, revision).map((key) => caches.delete(key)),
    );
}

async function fileOf(request: Request): Promise<Response> {
    const saved = await caches.match(request, { cacheName: cacheKey });

    if (saved !== undefined) {
        return saved;
    }

    const response = await fetch(request);

    if (response.ok) {
        const cache = await caches.open(cacheKey);
        await cache.put(request, response.clone());
    }

    return response;
}

async function pageOf(request: Request): Promise<Response> {
    try {
        return await fetch(request, {
            signal: AbortSignal.timeout(NETWORK_TIMEOUT_MS),
        });
    } catch (error) {
        const outcome =
            error instanceof DOMException && error.name === 'TimeoutError'
                ? 'timeout'
                : 'network-error';
        const shell = fallsBackToShell(outcome)
            ? await caches.match(shellUrl(scope), { cacheName: cacheKey })
            : undefined;

        return shell ?? Response.error();
    }
}

self.addEventListener('install', (event) => {
    event.waitUntil(saveShell().then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(dropOldCaches().then(() => self.clients.claim()));
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const route = routeOf({
        method: request.method,
        url: request.url,
        mode: request.mode,
        origin: self.location.origin,
        scopePath,
    });

    if (route === 'asset') {
        event.respondWith(fileOf(request));
    } else if (route === 'navigation') {
        event.respondWith(pageOf(request));
    }
});

// Every push shows a notice: the text of its kind when the body reads, the fixed one when it does not (iOS revokes a subscription
// whose push shows nothing). The body is only the kind, encrypted to this phone.
self.addEventListener('push', (event) => {
    const notice = noticeOfPush(event.data);
    const target = noticeTarget(payloadOf(event.data), scope);

    event.waitUntil(
        self.registration.showNotification(notice.title, {
            body: notice.body,
            tag: target.tag,
            icon: '/app-icon-192.png',
            data: { url: target.url },
        }),
    );
});

/** The decrypted body of a push, or undefined when there is none or it is not JSON. */
function payloadOf(data: PushMessageData | null): unknown {
    try {
        return data?.json();
    } catch {
        return undefined;
    }
}

// A tap opens the screen the notice is about: in a window of the company's app that is already open (which loads it again, so it
// shows what changed) or in a new one.
async function openApp(url: string): Promise<void> {
    const windows = await self.clients.matchAll({
        type: 'window',
        includeUncontrolled: true,
    });
    const open =
        windows[
            windowInScope(
                windows.map((win) => win.url),
                scopePath,
            )
        ];

    if (open === undefined) {
        await self.clients.openWindow(url);
    } else {
        await open.focus();
        await open.navigate(url);
    }
}

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const data: unknown = event.notification.data;
    const url =
        typeof data === 'object' &&
        data !== null &&
        'url' in data &&
        typeof data.url === 'string' &&
        data.url.startsWith(scope)
            ? data.url
            : punchUrl(scope);

    event.waitUntil(openApp(url));
});
