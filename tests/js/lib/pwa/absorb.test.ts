import { describe, expect, it } from 'vitest';
import { absorb } from '@/lib/pwa/absorb';
import { offlineStore } from '@/lib/pwa/offline-store';
import { memoryStore } from '../../support/memory-store';

const context = {
    tenantId: 't',
    employeeId: 'ana',
    centerId: 'c',
    agreementVersion: 3,
    rpId: 'humetria.test',
    offline: true,
    qrTypes: ['start', 'end'],
};

const answer = (employeeId = 'ana', ...names: string[]) => ({
    context: { ...context, employeeId },
    tickets: names.map((name, i) => ({
        ticket: `v1.${name}.mac`,
        issuedAt: 100 + i,
    })),
});

describe('absorb', () => {
    it('keeps the tickets and the context of an answer, joining the tickets already held', async () => {
        const store = offlineStore(memoryStore());

        expect(await absorb(store, answer('ana', 'a', 'b'))).toBe(true);
        expect(await absorb(store, answer('ana', 'c'))).toBe(true);

        expect((await store.tickets()).map((t) => t.ticket).sort()).toEqual([
            'v1.a.mac',
            'v1.b.mac',
            'v1.c.mac',
        ]);
        expect(await store.context()).toEqual(context);
    });

    it('does nothing with an answer that carries neither tickets nor context', async () => {
        const store = offlineStore(memoryStore());

        expect(await absorb(store, { name: 'Ana', marks: [] })).toBe(false);
        expect(await absorb(store, null)).toBe(false);
        expect(await store.context()).toBeNull();
    });

    it('keeps a context that came with no tickets, and tickets that came with no context', async () => {
        const store = offlineStore(memoryStore());

        await absorb(store, { context });
        expect(await store.context()).toEqual(context);

        await absorb(store, { tickets: [{ ticket: 'v1.z.mac', issuedAt: 5 }] });
        expect((await store.tickets()).map((t) => t.ticket)).toEqual([
            'v1.z.mac',
        ]);
        expect(await store.context()).toEqual(context);
    });

    it("forgets the person before it keeps another one's: their tickets, pass and key are not the new person's", async () => {
        const kv = memoryStore();
        const store = offlineStore(kv);
        await absorb(store, answer('ana', 'a'));
        await store.savePass({
            deviceKeyId: 'k',
            pass: 'v1.p.m',
            passId: 'p',
            passExpiresAt: '2026-11-26T09:00:00Z',
        });
        kv.data.set('device-key', 'key');
        await store.commitMark(
            {
                schema: 1,
                seq: 1,
                employeeId: 'ana',
                createdAt: 1,
                item: {
                    seq: 1,
                    type: 'start',
                    ticket: 'v1.x.mac',
                    center_id: 'c',
                    declared_at: 1,
                    agreement_version: 3,
                    signer: 'passkey',
                },
                state: 'queued',
                reason: null,
            },
            [{ ticket: 'v1.a.mac', issuedAt: 100 }],
        );

        await absorb(store, answer('beto', 'b'));

        expect((await store.tickets()).map((t) => t.ticket)).toEqual([
            'v1.b.mac',
        ]);
        expect((await store.context())?.employeeId).toBe('beto');
        expect(await store.pass()).toBeNull();
        expect(kv.data.has('device-key')).toBe(false);
        // Ana's mark waits for its delivery all the same.
        expect((await store.marks()).map((m) => m.employeeId)).toEqual(['ana']);
    });
});
