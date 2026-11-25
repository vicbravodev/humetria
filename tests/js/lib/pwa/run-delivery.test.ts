import { describe, expect, it, vi } from 'vitest';
import { runDelivery } from '@/lib/pwa/run-delivery';
import type { Answer } from '@/lib/pwa/run-delivery';
import { offlineStore } from '@/lib/pwa/offline-store';
import type { QueuedMark } from '@/lib/pwa/queue';
import { memoryStore } from '../../support/memory-store';

function mark(seq: number, employeeId = 'ana'): QueuedMark {
    return {
        schema: 1,
        seq,
        employeeId,
        createdAt: 1,
        item: {
            seq,
            type: 'start',
            ticket: `v1.t${seq}.mac`,
            center_id: 'c',
            declared_at: 1_794_050_400 + seq,
            agreement_version: 3,
            signer: 'device_key',
        },
        state: 'queued',
        reason: null,
    };
}

async function queued(...seqs: number[]) {
    const kv = memoryStore();
    const store = offlineStore(kv);

    for (const seq of seqs) {
        await store.commitMark(mark(seq), []);
    }

    return store;
}

const context = {
    tenantId: 't',
    employeeId: 'ana',
    centerId: 'c',
    agreementVersion: 3,
    rpId: 'humetria.test',
    offline: true,
};

/** The server accepting every mark it is sent, with a new ticket and the context. */
function accepting(): (body: { items: { seq: number }[] }) => Promise<Answer> {
    return ({ items }) =>
        Promise.resolve({
            status: 200,
            json: {
                results: items.map(({ seq }) => ({ seq, status: 'received' })),
                tickets: [{ ticket: 'v1.new.mac', issuedAt: 500 }],
                context,
            },
            retryAfter: null,
        });
}

const clock = () => {
    let at = 1_000;

    return () => (at += 1);
};

describe('runDelivery', () => {
    it('delivers what waits, takes it out of the queue, tells the person and keeps the new tickets and context', async () => {
        const store = await queued(1, 2);
        const post = vi.fn(accepting());

        const done = await runDelivery(store, { post, now: clock() });

        expect(post).toHaveBeenCalledTimes(1);
        expect(await store.marks()).toEqual([]);
        expect(done.notices.map((n) => [n.seq, n.kind])).toEqual([
            [1, 'received'],
            [2, 'received'],
        ]);
        expect(done.nextAt).toBeNull();
        expect((await store.tickets()).map((t) => t.ticket)).toEqual([
            'v1.new.mac',
        ]);
        expect(await store.context()).toEqual(context);
    });

    it('has nothing to do with an empty queue', async () => {
        const post = vi.fn(accepting());

        expect(
            await runDelivery(await queued(), { post, now: clock() }),
        ).toEqual({ notices: [], nextAt: null });
        expect(post).not.toHaveBeenCalled();
    });

    it('keeps in the phone what the server refused, with its reason, and the rest leave', async () => {
        const store = await queued(1, 2);
        const post = vi.fn(() =>
            Promise.resolve({
                status: 200,
                json: {
                    results: [
                        { seq: 1, status: 'received' },
                        { seq: 2, status: 'rejected', reason: 'wrong_center' },
                    ],
                },
                retryAfter: null,
            }),
        );

        await runDelivery(store, { post, now: clock() });

        expect(
            (await store.marks()).map((m) => [m.seq, m.state, m.reason]),
        ).toEqual([[2, 'kept', 'wrong_center']]);
    });
});

describe('runDelivery, when it goes wrong', () => {
    it('a poisoned batch: the 422 splits it, they go one by one, the bad one is malformed and the rest arrive', async () => {
        const store = await queued(1, 2, 3, 4, 5);
        const post = vi.fn(
            ({ items }: { items: { seq: number }[] }): Promise<Answer> => {
                if (items.length > 1 || items[0]?.seq === 3) {
                    return Promise.resolve({
                        status: 422,
                        json: { message: 'x', errors: {} },
                        retryAfter: null,
                    });
                }

                return accepting()({ items });
            },
        );

        const done = await runDelivery(store, { post, now: clock() });

        expect(post.mock.calls.map(([body]) => body.items.length)).toEqual([
            5, 1, 1, 1, 1, 1,
        ]);
        expect(
            (await store.marks()).map((m) => [m.seq, m.state, m.reason]),
        ).toEqual([[3, 'kept', 'malformed']]);
        expect(done.notices.map((n) => n.seq)).toEqual([1, 2, 4, 5]);
        expect(done.nextAt).toBeNull();
    });

    it('on a 500 or no network it deletes nothing, waits and says when to try again', async () => {
        const store = await queued(1);
        const failing = vi.fn(() =>
            Promise.resolve({ status: 500, json: null, retryAfter: null }),
        );

        const done = await runDelivery(store, { post: failing, now: clock() });

        expect(failing).toHaveBeenCalledTimes(1);
        expect((await store.marks()).map((m) => m.state)).toEqual(['queued']);
        expect(done.notices).toEqual([]);
        expect(done.nextAt).toBeGreaterThan(1_000);
        expect((await store.schedule()).attempts).toBe(1);

        const offline = vi.fn(() =>
            Promise.resolve({ status: null, json: null, retryAfter: null }),
        );

        // Not before its time: the second attempt waits.
        await runDelivery(store, { post: offline, now: () => 1_002 });
        expect(offline).not.toHaveBeenCalled();
    });

    it('goes at the time it was told to wait, and a 200 starts the wait again', async () => {
        const store = await queued(1);
        await runDelivery(store, {
            post: () =>
                Promise.resolve({ status: 503, json: null, retryAfter: null }),
            now: () => 1_000,
        });
        const { nextAt } = await runDelivery(store, {
            post: accepting(),
            now: () => 1_000,
        });

        expect(nextAt).toBe(6_000);

        await runDelivery(store, { post: accepting(), now: () => 6_000 });

        expect(await store.marks()).toEqual([]);
        expect((await store.schedule()).attempts).toBe(0);
    });

    it('does not spin on an answer that settles nothing', async () => {
        const store = await queued(1);
        const post = vi.fn(() =>
            Promise.resolve({
                status: 200,
                json: { results: [] },
                retryAfter: null,
            }),
        );

        await runDelivery(store, { post, now: clock() });

        expect(post).toHaveBeenCalledTimes(1);
        expect((await store.schedule()).attempts).toBe(1);
    });

    it('delivers one person at a time', async () => {
        const kv = memoryStore();
        const store = offlineStore(kv);
        await store.commitMark(mark(1, 'ana'), []);
        await store.commitMark(mark(2, 'beto'), []);
        const post = vi.fn(accepting());

        await runDelivery(store, { post, now: clock() });

        expect(
            post.mock.calls.map(([body]) => body.items.map((i) => i.seq)),
        ).toEqual([[1], [2]]);
    });
});
