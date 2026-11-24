import { describe, expect, it } from 'vitest';
import { prepareMark } from '@/lib/pwa/offline-mark';
import { offlineStore } from '@/lib/pwa/offline-store';
import {
    context,
    depsOf,
    input,
    readyStore,
    refuses,
} from '../../support/offline-mark';
import { memoryStore } from '../../support/memory-store';

describe('prepareMark, when it is not ready', () => {
    it('is not ready without a context', async () => {
        const store = offlineStore(memoryStore());
        await store.saveTickets([{ ticket: 'v1.a.mac', issuedAt: 1 }]);

        expect(await prepareMark(input, depsOf(store))).toEqual({
            kind: 'not-ready',
            why: 'no-context',
        });
    });

    it('is not ready when the company turned the mode off', async () => {
        const { store } = await readyStore(3, { offline: false });

        expect(await prepareMark(input, depsOf(store))).toEqual({
            kind: 'not-ready',
            why: 'disabled',
        });
    });

    it('is not ready when the person has not accepted the agreement', async () => {
        const store = offlineStore(memoryStore());
        await store.saveContext({ ...context, agreementVersion: null });
        await store.saveTickets([{ ticket: 'v1.a.mac', issuedAt: 1 }]);

        expect(await prepareMark(input, depsOf(store))).toEqual({
            kind: 'not-ready',
            why: 'agreement',
        });
    });

    it('is not ready without tickets, and touches nothing', async () => {
        const { store, kv } = await readyStore(0);

        expect(await prepareMark(input, depsOf(store))).toEqual({
            kind: 'not-ready',
            why: 'tickets',
        });
        expect(
            [...kv.data.keys()].filter((k) => k.startsWith('mark/')),
        ).toEqual([]);
    });
});

describe('prepareMark, spending tickets and numbering', () => {
    it('spends the newest ticket and does not use it again on the next mark', async () => {
        const { store } = await readyStore(3);

        const first = await prepareMark(input, depsOf(store));
        const second = await prepareMark(input, depsOf(store));

        expect(first.kind === 'saved' && first.mark.item.ticket).toBe(
            'v1.t2.mac',
        );
        expect(second.kind === 'saved' && second.mark.item.ticket).toBe(
            'v1.t1.mac',
        );
        expect((await store.tickets()).map((t) => t.ticket)).toEqual([
            'v1.t0.mac',
        ]);
    });

    it('numbers the marks 1, 2, 3', async () => {
        const { store } = await readyStore(3);

        for (const seq of [1, 2, 3]) {
            const saved = await prepareMark(input, depsOf(store));

            expect(saved.kind === 'saved' && saved.mark.seq).toBe(seq);
        }
    });

    it('spends no ticket and takes no seq when the write fails', async () => {
        const { store, kv } = await readyStore(2);
        kv.failNext();

        expect(await prepareMark(input, depsOf(store))).toEqual({
            kind: 'failed',
        });
        expect((await store.tickets()).map((t) => t.ticket)).toEqual([
            'v1.t1.mac',
            'v1.t0.mac',
        ]);
        expect(await store.nextSeq()).toBe(1);
    });

    it('is cancelled, with no device key to offer, when the passkey does not sign', async () => {
        const { store } = await readyStore(1);

        expect(await prepareMark(input, depsOf(store, refuses))).toEqual({
            kind: 'cancelled',
            deviceKey: false,
        });
        expect((await store.tickets()).length).toBe(1);
    });

    it('is cancelled when the passkey fails in any other way, since plan B is the same way out', async () => {
        const { store } = await readyStore(1);

        expect(
            await prepareMark(
                input,
                depsOf(store, () => Promise.reject(new Error('boom'))),
            ),
        ).toEqual({ kind: 'cancelled', deviceKey: false });
    });

    it('is failed when the text cannot be written', async () => {
        const { store } = await readyStore(1, { tenantId: 'a|b' });

        expect(await prepareMark(input, depsOf(store))).toEqual({
            kind: 'failed',
        });
    });
});
