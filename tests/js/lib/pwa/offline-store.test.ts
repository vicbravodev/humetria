import { describe, expect, it } from 'vitest';
import { offlineStore } from '@/lib/pwa/offline-store';
import { QUEUE_SCHEMA } from '@/lib/pwa/queue';
import type { QueuedMark } from '@/lib/pwa/queue';
import { memoryStore } from '../../support/memory-store';

function mark(seq: number, employeeId = 'ana'): QueuedMark {
    return {
        schema: QUEUE_SCHEMA,
        seq,
        employeeId,
        createdAt: 1_794_050_400_000,
        item: {
            seq,
            type: 'start',
            ticket: `v1.t${seq}.mac`,
            center_id: 'c',
            declared_at: 1_794_050_400,
            agreement_version: 3,
            signer: 'device_key',
        },
        state: 'queued',
        reason: null,
    };
}

const context = {
    tenantId: 't',
    employeeId: 'ana',
    centerId: 'c',
    agreementVersion: 3,
    rpId: 'humetria.test',
    offline: true,
    qrTypes: ['start', 'end'],
};

const pass = {
    deviceKeyId: 'k',
    pass: 'v1.p.m',
    passId: 'p',
    passExpiresAt: '2026-11-25T10:00:00.000Z',
};

const tickets = [{ ticket: 'v1.a.mac', issuedAt: 100 }];

describe('the marks', () => {
    it('keeps a mark under its seq and reads it back', async () => {
        const kv = memoryStore();
        const store = offlineStore(kv);

        await store.commitMark(mark(1), []);

        expect(await store.marks()).toEqual([mark(1)]);
        expect(kv.data.has('mark/1')).toBe(true);
    });

    it('ignores a record that is damaged and leaves one of a newer schema alone', async () => {
        const kv = memoryStore();
        const store = offlineStore(kv);
        kv.data.set('mark/1', { nonsense: true });
        kv.data.set('mark/2', { ...mark(2), schema: QUEUE_SCHEMA + 1 });
        kv.data.set('mark/3', mark(3));

        expect((await store.marks()).map((m) => m.seq)).toEqual([3]);
        // Neither deleted nor rewritten by a settle that does not name it.
        await store.settle({ puts: [], removed: [3], notices: [] });
        expect(kv.data.has('mark/2')).toBe(true);
        expect(kv.data.has('mark/1')).toBe(true);
    });

    it('settles a delivery in one go: what leaves goes, what is refused is written again', async () => {
        const kv = memoryStore();
        const store = offlineStore(kv);
        await store.commitMark(mark(1), []);
        await store.commitMark(mark(2), []);

        await store.settle({
            puts: [{ ...mark(2), state: 'kept', reason: 'wrong_center' }],
            removed: [1],
            notices: [],
        });

        expect(await store.marks()).toEqual([
            { ...mark(2), state: 'kept', reason: 'wrong_center' },
        ]);
    });

    it('discards one mark and nothing else', async () => {
        const store = offlineStore(memoryStore());
        await store.commitMark(mark(1), []);
        await store.commitMark(mark(2), []);

        await store.discard(1);

        expect((await store.marks()).map((m) => m.seq)).toEqual([2]);
    });
});

describe('commitMark', () => {
    it('writes the mark, the tickets that are left and the next seq together', async () => {
        const store = offlineStore(memoryStore());

        await store.commitMark(mark(1), tickets);

        expect(await store.tickets()).toEqual(tickets);
        expect(await store.nextSeq()).toBe(2);
    });

    it('writes nothing when the write fails: no mark, no spent ticket, no new seq', async () => {
        const kv = memoryStore();
        const store = offlineStore(kv);
        await store.saveTickets(tickets);
        kv.failNext();

        await expect(store.commitMark(mark(1), [])).rejects.toThrow();

        expect(await store.marks()).toEqual([]);
        expect(await store.tickets()).toEqual(tickets);
        expect(await store.nextSeq()).toBe(1);
    });
});

describe('what the store keeps besides the marks', () => {
    it('gives defaults for what was never written', async () => {
        const store = offlineStore(memoryStore());

        expect(await store.tickets()).toEqual([]);
        expect(await store.context()).toBeNull();
        expect(await store.pass()).toBeNull();
        expect(await store.deviceKey()).toBeNull();
        expect(await store.practice()).toBeNull();
        expect(await store.nextSeq()).toBe(1);
        expect(await store.schedule()).toEqual({
            nextAttemptAt: 0,
            attempts: 0,
            isolating: 0,
        });
        expect(await store.ticketsAt()).toBeNull();
    });

    it('reads back what it wrote', async () => {
        const store = offlineStore(memoryStore());
        const key = (
            await crypto.subtle.generateKey(
                { name: 'ECDSA', namedCurve: 'P-256' },
                false,
                ['sign'],
            )
        ).privateKey;
        const schedule = {
            nextAttemptAt: 5,
            attempts: 2,
            isolating: 1,
        };

        await store.saveTickets(tickets);
        await store.saveContext(context);
        await store.savePass(pass);
        await store.saveDeviceKey(key);
        await store.savePractice({ result: 'passkey', at: 77 });
        await store.saveTicketsAt(9);
        await store.saveSchedule(schedule);

        expect(await store.tickets()).toEqual(tickets);
        expect(await store.context()).toEqual(context);
        expect(await store.pass()).toEqual(pass);
        expect(await store.deviceKey()).toBe(key);
        expect(await store.practice()).toEqual({ result: 'passkey', at: 77 });
        expect(await store.ticketsAt()).toBe(9);
        expect(await store.schedule()).toEqual(schedule);
    });

    it('ignores a value that is damaged, and never forces it', async () => {
        const kv = memoryStore();
        const store = offlineStore(kv);
        kv.data.set('tickets', 'many');
        kv.data.set('context', 7);
        kv.data.set('pass', { pass: 1 });
        kv.data.set('device-key', 'a key');
        kv.data.set('practice', { result: 'maybe', at: 1 });
        kv.data.set('seq', 'two');
        kv.data.set('schedule', { attempts: 'many' });
        kv.data.set('tickets-at', 'yesterday');

        expect(await store.tickets()).toEqual([]);
        expect(await store.context()).toBeNull();
        expect(await store.pass()).toBeNull();
        expect(await store.deviceKey()).toBeNull();
        expect(await store.practice()).toBeNull();
        expect(await store.nextSeq()).toBe(1);
        expect((await store.schedule()).attempts).toBe(0);
        expect(await store.ticketsAt()).toBeNull();
    });

    it('keeps when tickets were asked for apart from the delivery schedule: neither writes the other', async () => {
        const store = offlineStore(memoryStore());

        await store.saveTicketsAt(1_000);
        await store.saveSchedule({
            nextAttemptAt: 5,
            attempts: 2,
            isolating: 0,
        });

        expect(await store.ticketsAt()).toBe(1_000);

        await store.saveTicketsAt(2_000);

        expect((await store.schedule()).attempts).toBe(2);
    });
});

describe('dropDeviceKey', () => {
    it('takes out the key and its pass together, and nothing else', async () => {
        const kv = memoryStore();
        const store = offlineStore(kv);
        await store.savePass(pass);
        await store.saveTickets(tickets);
        kv.data.set('device-key', 'key');

        await store.dropDeviceKey();

        expect(await store.pass()).toBeNull();
        expect(kv.data.has('device-key')).toBe(false);
        expect(await store.tickets()).toEqual(tickets);
    });
});

describe('forgetPerson', () => {
    it("takes out of the phone what is the person's and keeps the marks that wait", async () => {
        const kv = memoryStore();
        const store = offlineStore(kv);
        await store.commitMark(mark(1), tickets);
        await store.saveContext(context);
        await store.savePass(pass);
        await store.savePractice({ result: 'failed', at: 1 });
        await store.saveSchedule({
            nextAttemptAt: 0,
            attempts: 1,
            isolating: 0,
        });
        await store.saveTicketsAt(3);
        kv.data.set('device-key', 'key');

        await store.forgetPerson();

        expect(await store.tickets()).toEqual([]);
        expect(await store.context()).toBeNull();
        expect(await store.pass()).toBeNull();
        expect(kv.data.has('device-key')).toBe(false);
        expect(await store.practice()).toBeNull();
        expect((await store.marks()).map((m) => m.seq)).toEqual([1]);
        expect(await store.nextSeq()).toBe(2);
        expect((await store.schedule()).attempts).toBe(1);
        expect(await store.ticketsAt()).toBeNull();
    });
});
