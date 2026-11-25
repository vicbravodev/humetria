import { staleCaches, workerSlug } from '@/lib/pwa/sw-rules';

/** The part of `navigator.serviceWorker` the registration uses: a plain object stands in for it in the tests. */
export type WorkerContainer = {
    register(url: string, options: { scope: string }): Promise<unknown>;
    getRegistration(
        scope: string,
    ): Promise<{ unregister(): Promise<boolean> } | undefined>;
};

type MetaSource = {
    querySelector(
        selector: string,
    ): { getAttribute(name: string): string | null } | null;
};

/** The revision PHP is running, which names the worker's version: a new `?v=` is a new script for the browser. */
export function revisionOf(page: MetaSource | undefined): string {
    return (
        page
            ?.querySelector('meta[name="app-revision"]')
            ?.getAttribute('content') || 'unknown'
    );
}

/** The page says the worker is switched off (`PWA_SW_KILL`): it must not be registered again. */
export function killedIn(page: MetaSource | undefined): boolean {
    return page?.querySelector('meta[name="pwa-sw-kill"]') != null;
}

/** The part of `caches` the switch-off uses. */
export type CacheStore = {
    keys(): Promise<string[]>;
    delete(key: string): Promise<boolean>;
};

/**
 * Puts the worker of the company in the page's address in charge of `/me/{slug}`, or, with the switch on, takes out the one
 * that is there and the caches of the company (the worker that undoes itself may never get to run: the page unregistering
 * its registration can win). Idempotent: the browser keeps one registration per scope. A failure is swallowed, the app goes
 * on online.
 */
export async function registerWorkerApp({
    pathname,
    revision,
    killed,
    container,
    dev,
    caches,
}: {
    pathname: string;
    revision: string;
    killed: boolean;
    container: WorkerContainer | undefined;
    dev: boolean;
    caches?: CacheStore | undefined;
}): Promise<void> {
    const slug = workerSlug(pathname);

    if (dev || container === undefined || slug === null) {
        return;
    }

    const scope = `/me/${slug}`;

    try {
        if (killed) {
            await dropCaches(caches, slug);
            await (await container.getRegistration(scope))?.unregister();

            return;
        }

        await container.register(`${scope}/sw.js?v=${revision}`, { scope });
    } catch {
        // Offline marks are an extra: a browser that refuses the worker keeps working online.
    }
}

/** Every cache of the company, whatever its revision (an empty one names none, so none is kept). */
async function dropCaches(
    caches: CacheStore | undefined,
    slug: string,
): Promise<void> {
    if (caches === undefined) {
        return;
    }

    await Promise.all(
        staleCaches(await caches.keys(), slug, '').map((key) =>
            caches.delete(key),
        ),
    );
}
