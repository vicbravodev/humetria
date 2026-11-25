import { describe, expect, it } from 'vitest';
import {
    delayFor,
    nextBatch,
    retryAfterOf,
    scheduleAfter,
} from '@/lib/pwa/delivery';
import type { Outcome, Schedule } from '@/lib/pwa/delivery';
import { QUEUE_SCHEMA } from '@/lib/pwa/queue';
import type { QueuedMark } from '@/lib/pwa/queue';

function mark(seq: number): QueuedMark {
    return {
        schema: QUEUE_SCHEMA,
        seq,
        employeeId: 'ana',
        createdAt: 1_794_050_400_000,
        item: {
            seq,
            type: 'start',
            ticket: `v1.ticket-${seq}.mac`,
            center_id: 'center-1',
            declared_at: 1_794_050_400 + seq,
            agreement_version: 3,
            signer: 'device_key',
        },
        state: 'queued',
        reason: null,
    };
}

const calm: Schedule = {
    nextAttemptAt: 0,
    attempts: 0,
    isolating: 0,
};

describe('delayFor', () => {
    it('grows 5, 10 and 20 seconds up to 15 minutes', () => {
        expect([1, 2, 3, 4].map((n) => delayFor(n, null))).toEqual([
            5_000, 10_000, 20_000, 40_000,
        ]);
        expect(delayFor(9, null)).toBe(900_000);
        expect(delayFor(30, null)).toBe(900_000);
    });

    it('lets Retry-After rule when it is longer', () => {
        expect(delayFor(1, 120)).toBe(120_000);
        expect(delayFor(1, 2)).toBe(5_000);
    });
});

describe('retryAfterOf', () => {
    it('reads the seconds of the header, and nothing else', () => {
        expect(retryAfterOf('120')).toBe(120);
        expect(retryAfterOf(null)).toBeNull();
        expect(retryAfterOf('Wed, 21 Oct 2026 07:28:00 GMT')).toBeNull();
        expect(retryAfterOf('-5')).toBeNull();
    });
});

describe('scheduleAfter', () => {
    const now = 1_000_000;
    const retry: Outcome = { kind: 'retry', retryAfter: null };
    const applied: Outcome = {
        kind: 'applied',
        results: [],
        tickets: [],
        context: null,
    };

    it('waits longer after each failure and a 200 starts the wait again', () => {
        const first = scheduleAfter(calm, retry, { size: 2, now });
        const second = scheduleAfter(first, retry, { size: 2, now });

        expect(first).toMatchObject({
            attempts: 1,
            nextAttemptAt: now + 5_000,
        });
        expect(second).toMatchObject({
            attempts: 2,
            nextAttemptAt: now + 10_000,
        });
        expect(scheduleAfter(second, applied, { size: 2, now })).toMatchObject({
            attempts: 0,
            nextAttemptAt: now,
        });
    });

    it('sends the next ones one by one after a split, as many as were in the batch', () => {
        const split = scheduleAfter(
            calm,
            { kind: 'split', size: 5 },
            { size: 5, now },
        );

        expect(split).toMatchObject({ isolating: 5, nextAttemptAt: now });

        let schedule = split;

        for (let i = 0; i < 5; i += 1) {
            expect(nextBatch([mark(i + 1)], schedule, now)).toHaveLength(1);
            schedule = scheduleAfter(
                schedule,
                i === 0 ? { kind: 'malformed' } : applied,
                { size: 1, now },
            );
        }

        expect(schedule.isolating).toBe(0);
    });
});
