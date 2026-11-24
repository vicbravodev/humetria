import { describe, expect, it } from 'vitest';
import {
    QUEUE_SCHEMA,
    apply,
    forPerson,
    kept,
    keep,
    pending,
    readMark,
} from '@/lib/pwa/queue';
import type { DeliveryItem, QueuedMark } from '@/lib/pwa/queue';

function item(seq: number): DeliveryItem {
    return {
        seq,
        type: 'start',
        ticket: `v1.ticket-${seq}.mac`,
        center_id: 'center-1',
        declared_at: 1_794_050_400 + seq,
        agreement_version: 3,
        signer: 'device_key',
        device_key_id: 'key-1',
        signature: 'sig',
        pass: 'v1.pass.mac',
    };
}

function mark(seq: number, over: Partial<QueuedMark> = {}): QueuedMark {
    return {
        schema: QUEUE_SCHEMA,
        seq,
        employeeId: 'ana',
        createdAt: 1_794_050_400_000 + seq,
        item: item(seq),
        state: 'queued',
        reason: null,
        ...over,
    };
}

describe('apply', () => {
    const marks = [1, 2, 3, 4, 5, 6, 7, 8].map((seq) => mark(seq));

    it('takes out what the server received, or already had received', () => {
        const outcome = apply(marks, [
            { seq: 1, status: 'received' },
            { seq: 2, status: 'duplicate', stored_status: 'received' },
        ]);

        expect(outcome.removed).toEqual([1, 2]);
        expect(outcome.puts).toEqual([]);
        expect(outcome.notices).toEqual([
            { seq: 1, employeeId: 'ana', kind: 'received' },
            { seq: 2, employeeId: 'ana', kind: 'received' },
        ]);
    });

    it('says so when the mark was already on the server from a connected tap', () => {
        const outcome = apply(marks, [
            {
                seq: 3,
                status: 'duplicate',
                reason: 'already_recorded',
                stored_status: 'received',
            },
        ]);

        expect(outcome.removed).toEqual([3]);
        expect(outcome.notices).toEqual([
            { seq: 3, employeeId: 'ana', kind: 'already_recorded' },
        ]);
    });

    it('keeps in the phone, with their reason, the marks the server did not accept', () => {
        const outcome = apply(marks, [
            {
                seq: 4,
                status: 'duplicate',
                reason: 'sequence_conflict',
                stored_status: 'rejected',
            },
            { seq: 5, status: 'rejected', reason: 'wrong_center' },
            { seq: 6, status: 'rejected', reason: 'ticket_invalid' },
            { seq: 7, status: 'rejected', reason: 'signature_invalid' },
            { seq: 8, status: 'conflict', reason: 'ticket_reused' },
        ]);

        expect(outcome.removed).toEqual([]);
        expect(outcome.notices).toEqual([]);
        expect(
            outcome.puts.map(({ seq, state, reason }) => [seq, state, reason]),
        ).toEqual([
            [4, 'kept', 'sequence_conflict'],
            [5, 'kept', 'wrong_center'],
            [6, 'kept', 'ticket_invalid'],
            [7, 'kept', 'signature_invalid'],
            [8, 'kept', 'ticket_reused'],
        ]);
    });

    it('leaves a mark without a result queued, and a result without a mark unseen', () => {
        const outcome = apply(marks.slice(0, 2), [
            { seq: 99, status: 'received' },
        ]);

        expect(outcome).toEqual({ puts: [], removed: [], notices: [] });
    });

    it('does not touch a mark that was already refused', () => {
        const refused = mark(1, { state: 'kept', reason: 'wrong_center' });

        expect(apply([refused], [{ seq: 1, status: 'received' }])).toEqual({
            puts: [],
            removed: [],
            notices: [],
        });
    });

    it('keeps a mark whose status it does not know, and gives none a reason of nothing', () => {
        expect(
            apply([mark(1)], [{ seq: 1, status: 'rejected' }]).puts[0]?.reason,
        ).toBeNull();
    });
});

describe('keep', () => {
    it('refuses a mark with a reason of the phone, the one of a malformed mark', () => {
        expect(keep(mark(1), 'malformed')).toMatchObject({
            state: 'kept',
            reason: 'malformed',
            schema: QUEUE_SCHEMA,
        });
    });
});

describe('forPerson, pending and kept', () => {
    const marks = [
        mark(3, { employeeId: 'ana' }),
        mark(1, { employeeId: 'ana' }),
        mark(2, { employeeId: 'beto' }),
        mark(5, { employeeId: 'ana', state: 'kept', reason: 'wrong_center' }),
        mark(4, { employeeId: 'ana', state: 'kept', reason: 'malformed' }),
    ];

    it('lists only the marks of the person on screen', () => {
        expect(forPerson(marks, 'beto').map((m) => m.seq)).toEqual([2]);
        expect(forPerson(marks, 'ana')).toHaveLength(4);
        expect(forPerson(marks, null)).toEqual([]);
    });

    it('puts the queued in order of seq and does not count the refused', () => {
        expect(pending(marks).map((m) => m.seq)).toEqual([1, 2, 3]);
    });

    it('puts the refused in order of seq', () => {
        expect(kept(marks).map((m) => m.seq)).toEqual([4, 5]);
    });
});

describe('readMark', () => {
    it('reads a mark of the schema it knows', () => {
        expect(readMark(mark(1))).toEqual(mark(1));
    });

    it('says a mark of a newer schema is newer, so it is neither delivered nor rewritten', () => {
        expect(readMark({ ...mark(1), schema: QUEUE_SCHEMA + 1 })).toBe(
            'newer',
        );
    });

    it('gives nothing for a record that is damaged', () => {
        expect(readMark({ ...mark(1), schema: undefined })).toBeNull();
        expect(readMark({ ...mark(1), schema: 0 })).toBeNull();
        expect(readMark({ ...mark(1), schema: 'one' })).toBeNull();
        expect(readMark(null)).toBeNull();
        expect(readMark('mark')).toBeNull();
        expect(readMark([])).toBeNull();
        expect(readMark({ ...mark(1), state: 'sending' })).toBeNull();
        expect(readMark({ ...mark(1), seq: '1' })).toBeNull();
        expect(readMark({ ...mark(1), employeeId: 7 })).toBeNull();
        expect(readMark({ ...mark(1), createdAt: 'now' })).toBeNull();
        expect(readMark({ ...mark(1), reason: 7 })).toBeNull();
        expect(readMark({ ...mark(1), item: null })).toBeNull();
        expect(readMark({ ...mark(1), item: { seq: 1 } })).toBeNull();
        expect(
            readMark({ ...mark(1), item: { ...item(1), type: 'lunch' } }),
        ).toBeNull();
        expect(
            readMark({ ...mark(1), item: { ...item(1), signer: 'face' } }),
        ).toBeNull();
    });
});
