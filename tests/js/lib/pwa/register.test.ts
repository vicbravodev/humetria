import { describe, expect, it, vi } from 'vitest';
import { killedIn, registerWorkerApp, revisionOf } from '@/lib/pwa/register';

function container(existing = true) {
    const unregister = vi.fn(() => Promise.resolve(true));
    const register = vi.fn(() => Promise.resolve({}));
    const getRegistration = vi.fn(() =>
        Promise.resolve(existing ? { unregister } : undefined),
    );

    return { register, getRegistration, unregister };
}

function cacheStore(keys: string[]) {
    const removed: string[] = [];

    return {
        removed,
        // As the real `caches`: what was deleted is no longer listed (the sweep runs twice).
        keys: () =>
            Promise.resolve(keys.filter((key) => !removed.includes(key))),
        delete: (key: string) => {
            removed.push(key);

            return Promise.resolve(true);
        },
    };
}

const base = {
    pathname: '/me/acme/punch',
    revision: 'abc',
    killed: false,
    dev: false,
};

describe('registerWorkerApp', () => {
    it('registers the worker of the company, with the revision, over the scope without a trailing slash', async () => {
        const worker = container();

        await registerWorkerApp({ ...base, container: worker });

        expect(worker.register).toHaveBeenCalledWith('/me/acme/sw.js?v=abc', {
            scope: '/me/acme',
        });
    });

    it('registers from the start of the person too', async () => {
        const worker = container();

        await registerWorkerApp({
            ...base,
            pathname: '/me/acme',
            container: worker,
        });

        expect(worker.register).toHaveBeenCalledWith('/me/acme/sw.js?v=abc', {
            scope: '/me/acme',
        });
    });

    it('does nothing while Vite serves hot', async () => {
        const worker = container();

        await registerWorkerApp({ ...base, dev: true, container: worker });

        expect(worker.register).not.toHaveBeenCalled();
    });

    it('does nothing without a service worker container', async () => {
        await expect(
            registerWorkerApp({ ...base, container: undefined }),
        ).resolves.toBeUndefined();
    });

    it.each(['/a/acme/punch', '/admin', '/me/', '/'])(
        'does nothing outside the pages of a company (%s)',
        async (pathname) => {
            const worker = container();

            await registerWorkerApp({ ...base, pathname, container: worker });

            expect(worker.register).not.toHaveBeenCalled();
            expect(worker.getRegistration).not.toHaveBeenCalled();
        },
    );

    it('switched off, it neither registers nor keeps the one that is there', async () => {
        const worker = container();

        await registerWorkerApp({ ...base, killed: true, container: worker });

        expect(worker.register).not.toHaveBeenCalled();
        expect(worker.getRegistration).toHaveBeenCalledWith('/me/acme');
        expect(worker.unregister).toHaveBeenCalledOnce();
    });

    it('switched off, also drops the caches of its company and no other', async () => {
        const worker = container();
        const store = cacheStore([
            'humetria-pwa-acme-abc',
            'humetria-pwa-acme-old',
            'humetria-pwa-acme-industrial-abc',
            'humetria-pwa-other-abc',
        ]);

        await registerWorkerApp({
            ...base,
            killed: true,
            container: worker,
            caches: store,
        });

        expect(store.removed).toEqual([
            'humetria-pwa-acme-abc',
            'humetria-pwa-acme-old',
        ]);
    });

    it('not switched off, leaves the caches alone', async () => {
        const store = cacheStore(['humetria-pwa-acme-abc']);

        await registerWorkerApp({
            ...base,
            container: container(),
            caches: store,
        });

        expect(store.removed).toEqual([]);
    });

    it('switched off with nothing registered, there is nothing to undo', async () => {
        const worker = container(false);

        await registerWorkerApp({ ...base, killed: true, container: worker });

        expect(worker.unregister).not.toHaveBeenCalled();
    });

    it('swallows a registration that fails: the app goes on online', async () => {
        const worker = container();
        worker.register.mockRejectedValue(new Error('refused'));

        await expect(
            registerWorkerApp({ ...base, container: worker }),
        ).resolves.toBeUndefined();
    });

    it('swallows an unregistration that fails', async () => {
        const worker = container();
        worker.getRegistration.mockRejectedValue(new Error('refused'));

        await expect(
            registerWorkerApp({ ...base, killed: true, container: worker }),
        ).resolves.toBeUndefined();
    });
});

function documentWith(metas: Record<string, string>) {
    return {
        querySelector: (selector: string) => {
            const name = /meta\[name="([^"]+)"\]/.exec(selector)?.[1];
            const content = name === undefined ? undefined : metas[name];

            return content === undefined
                ? null
                : { getAttribute: () => content };
        },
    };
}

describe('registerWorkerApp, switched off', () => {
    it('switched off, sweeps the caches again once the old worker has had its last word: it may write a file after the first sweep', async () => {
        vi.useFakeTimers();
        const removed: string[] = [];
        // The first sweep finds the cache; the old worker, still in charge of the page's last requests, writes it back.
        const sweeps = [['humetria-pwa-acme-abc'], ['humetria-pwa-acme-abc']];
        const store = {
            keys: () => Promise.resolve(sweeps.shift() ?? []),
            delete: (key: string) => {
                removed.push(key);

                return Promise.resolve(true);
            },
        };

        const done = registerWorkerApp({
            ...base,
            killed: true,
            container: container(),
            caches: store,
        });
        await vi.advanceTimersByTimeAsync(5_000);
        await done;
        vi.useRealTimers();

        expect(removed).toEqual([
            'humetria-pwa-acme-abc',
            'humetria-pwa-acme-abc',
        ]);
    });
});

describe('revisionOf', () => {
    it('is the revision the page says it runs', () => {
        expect(revisionOf(documentWith({ 'app-revision': 'f00d' }))).toBe(
            'f00d',
        );
    });

    it('is unknown when the page says nothing', () => {
        expect(revisionOf(documentWith({}))).toBe('unknown');
        expect(revisionOf(undefined)).toBe('unknown');
    });
});

describe('killedIn', () => {
    it('is true when the page carries the switch', () => {
        expect(killedIn(documentWith({ 'pwa-sw-kill': '1' }))).toBe(true);
    });

    it('is false without it', () => {
        expect(killedIn(documentWith({}))).toBe(false);
        expect(killedIn(undefined)).toBe(false);
    });
});
