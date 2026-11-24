import { describe, expect, it, vi } from 'vitest';
import { createDeviceKey } from '@/lib/pwa/device-key';
import { offlineStore } from '@/lib/pwa/offline-store';
import { renewPass } from '@/lib/pwa/renew-pass';
import { memoryStore } from '../../support/memory-store';

const old = {
    deviceKeyId: 'k-1',
    pass: 'v1.old.mac',
    passId: 'p-1',
    passExpiresAt: '2026-11-25T09:00:00Z',
};
const renewed = {
    ...old,
    pass: 'v1.new.mac',
    passId: 'p-2',
    passExpiresAt: '2026-11-26T09:00:00Z',
};

async function holding() {
    const store = offlineStore(memoryStore());
    await store.savePass(old);
    await store.saveDeviceKey(
        (await createDeviceKey(crypto.subtle)).privateKey,
    );

    return store;
}

const answering = (status: number, body: unknown) =>
    vi.fn<typeof fetch>(() =>
        Promise.resolve(new Response(JSON.stringify(body), { status })),
    );

function sentBody(fetcher: ReturnType<typeof answering>): string {
    const body = fetcher.mock.calls[0]?.[1]?.body;

    return typeof body === 'string' ? body : '';
}

describe('renewPass', () => {
    it('asks with the key it holds and keeps the new pass', async () => {
        const store = await holding();
        const fetcher = answering(200, renewed);

        await renewPass(store, '/me/acme/device-key/pass', {
            fetcher,
            xsrf: 'token',
        });

        expect(JSON.parse(String(sentBody(fetcher)))).toEqual({
            device_key_id: 'k-1',
        });
        expect(await store.pass()).toEqual(renewed);
        expect(await store.deviceKey()).not.toBeNull();
    });

    it('drops the key and its pass when the server no longer knows the key', async () => {
        const store = await holding();

        await renewPass(store, '/x', {
            fetcher: answering(404, {}),
            xsrf: null,
        });

        expect(await store.pass()).toBeNull();
        expect(await store.deviceKey()).toBeNull();
    });

    it('changes nothing on 401, 403, 419, 5xx, a pass it cannot read or no network', async () => {
        for (const [status, body] of [
            [401, {}],
            [403, {}],
            [419, {}],
            [500, {}],
            [200, { pass: 5 }],
        ] as const) {
            const store = await holding();

            await renewPass(store, '/x', {
                fetcher: answering(status, body),
                xsrf: null,
            });

            expect(await store.pass()).toEqual(old);
            expect(await store.deviceKey()).not.toBeNull();
        }

        const store = await holding();
        await renewPass(store, '/x', {
            fetcher: () => Promise.reject(new TypeError('down')),
            xsrf: null,
        });
        expect(await store.pass()).toEqual(old);
    });

    it('asks nothing without a key and a pass', async () => {
        const fetcher = answering(200, renewed);
        const store = offlineStore(memoryStore());

        await renewPass(store, '/x', { fetcher, xsrf: null });
        await store.savePass(old);
        await renewPass(store, '/x', { fetcher, xsrf: null });

        expect(fetcher).not.toHaveBeenCalled();
    });
});
