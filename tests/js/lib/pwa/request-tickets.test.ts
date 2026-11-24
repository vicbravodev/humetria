import { describe, expect, it, vi } from 'vitest';
import { offlineStore } from '@/lib/pwa/offline-store';
import { QUEUE_SCHEMA } from '@/lib/pwa/queue';
import { requestTickets } from '@/lib/pwa/request-tickets';
import type { Answer } from '@/lib/pwa/run-delivery';
import { memoryStore } from '../../support/memory-store';

const context = {
    tenantId: 't',
    employeeId: 'ana',
    centerId: 'c',
    agreementVersion: 3,
    rpId: 'humetria.test',
    offline: true,
};

const NOW = 1_794_050_400_000;

async function holding(tickets = [{ ticket: 'v1.old.mac', issuedAt: 100 }]) {
    const store = offlineStore(memoryStore());
    await store.saveContext(context);
    await store.saveTickets(tickets);

    return store;
}

const gives = (answer: Answer) => vi.fn(() => Promise.resolve(answer));

const fresh: Answer = {
    status: 200,
    json: {
        tickets: [{ ticket: 'v1.new.mac', issuedAt: 200 }],
        context: { ...context, agreementVersion: 4 },
    },
    retryAfter: null,
};

describe('requestTickets', () => {
    it('swaps its newest ticket for a new batch and keeps the context', async () => {
        const store = await holding();
        const post = gives(fresh);

        await requestTickets(store, { post, now: () => NOW });

        expect(post).toHaveBeenCalledWith({ ticket: 'v1.old.mac' });
        expect((await store.tickets()).map((t) => t.ticket)).toEqual([
            'v1.new.mac',
            'v1.old.mac',
        ]);
        expect((await store.context())?.agreementVersion).toBe(4);
    });

    it('remembers when it asked, apart from the delivery schedule, which a delivery writes on its own', async () => {
        const store = await holding();
        const post = gives(fresh);

        await requestTickets(store, { post, now: () => NOW });
        // A delivery in between saves its schedule: it must not make the next ask come sooner than 30 s.
        await store.saveSchedule({
            nextAttemptAt: 0,
            attempts: 0,
            isolating: 0,
        });
        await requestTickets(store, { post, now: () => NOW + 10_000 });

        expect(await store.ticketsAt()).toBe(NOW);
        expect(post).toHaveBeenCalledTimes(1);
    });

    it('asks for nothing when it holds no ticket: the first ones come with a signed action', async () => {
        const post = gives(fresh);

        await requestTickets(await holding([]), { post, now: () => NOW });

        expect(post).not.toHaveBeenCalled();
    });

    it('does not ask twice within 30 seconds, and asks again at 30', async () => {
        const store = await holding();
        const post = gives(fresh);

        await requestTickets(store, { post, now: () => NOW });
        await requestTickets(store, { post, now: () => NOW + 29_000 });
        expect(post).toHaveBeenCalledTimes(1);

        await requestTickets(store, { post, now: () => NOW + 30_000 });
        expect(post).toHaveBeenCalledTimes(2);
    });

    it('tells the server how many marks of the person wait and the age of the oldest', async () => {
        const store = await holding();
        await store.commitMark(
            {
                schema: QUEUE_SCHEMA,
                seq: 1,
                employeeId: 'ana',
                createdAt: NOW - 20 * 60_000,
                item: {
                    seq: 1,
                    type: 'start',
                    ticket: 'v1.t1.mac',
                    center_id: 'c',
                    declared_at: 1,
                    agreement_version: 3,
                    signer: 'passkey',
                },
                state: 'queued',
                reason: null,
            },
            [{ ticket: 'v1.old.mac', issuedAt: 100 }],
        );
        const post = gives(fresh);

        await requestTickets(store, { post, now: () => NOW });

        expect(post).toHaveBeenCalledWith({
            ticket: 'v1.old.mac',
            queue: { count: 1, oldest_age_minutes: 20 },
        });
    });

    it('deletes nothing when the server says no or does not answer', async () => {
        for (const status of [403, 404, 422, 429, null]) {
            const store = await holding();

            await requestTickets(store, {
                post: gives({ status, json: null, retryAfter: null }),
                now: () => NOW,
            });

            expect((await store.tickets()).map((t) => t.ticket)).toEqual([
                'v1.old.mac',
            ]);
        }
    });
});
