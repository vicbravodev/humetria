import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import {
    JSON_HEADERS,
    MAX_BATCH,
    body,
    isNetworkFailure,
    nextBatch,
    outcomeOf,
    postInit,
} from '@/lib/pwa/delivery';
import type { Schedule } from '@/lib/pwa/delivery';
import { QUEUE_SCHEMA, apply, keep } from '@/lib/pwa/queue';
import type { DeliveryItem, QueuedMark } from '@/lib/pwa/queue';

/** What PHP answers and accepts: tests/Feature/Attendance/OfflineDeliveryTest.php and the envelope of OfflinePunchRequest. */
const fixture: {
    request: { items: DeliveryItem[] };
    response: unknown;
    envelope: { status: number; body: unknown }[];
} = JSON.parse(
    readFileSync('tests/Fixtures/attendance/offline-delivery-v1.json', 'utf8'),
);

function itemOf(seq: number): DeliveryItem {
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

function mark(seq: number, employeeId = 'ana', item = itemOf(seq)): QueuedMark {
    return {
        schema: QUEUE_SCHEMA,
        seq,
        employeeId,
        createdAt: 1_794_050_400_000,
        item,
        state: 'queued',
        reason: null,
    };
}

function answer(
    status: number | null,
    json: unknown = null,
    retryAfter: number | null = null,
) {
    return { status, json, retryAfter };
}

const calm: Schedule = {
    nextAttemptAt: 0,
    attempts: 0,
    isolating: 0,
};

describe('nextBatch', () => {
    const many = (n: number) =>
        Array.from({ length: n }, (_, i) => mark(i + 1));

    it('takes up to 20, and the 21st goes in the next batch', () => {
        expect(MAX_BATCH).toBe(20);
        expect(nextBatch(many(20), calm, 10)).toHaveLength(20);
        expect(nextBatch(many(21), calm, 10).map((m) => m.seq)).toEqual(
            many(21)
                .slice(0, 20)
                .map((m) => m.seq),
        );
    });

    it('is empty before the time of the next attempt, and goes at it', () => {
        const waiting = { ...calm, nextAttemptAt: 1_000 };

        expect(nextBatch(many(2), waiting, 999)).toEqual([]);
        expect(nextBatch(many(2), waiting, 1_000)).toHaveLength(2);
    });

    it('goes in order of seq even when the marks were saved out of order', () => {
        const marks = [mark(3), mark(1), mark(2)];

        expect(nextBatch(marks, calm, 0).map((m) => m.seq)).toEqual([1, 2, 3]);
    });

    it('carries the marks of one person only, that of the oldest mark, never a mixed envelope', () => {
        const marks = [
            mark(1, 'ana'),
            mark(2, 'beto'),
            mark(3, 'ana'),
            mark(4, 'beto'),
        ];

        expect(nextBatch(marks, calm, 0).map((m) => m.seq)).toEqual([1, 3]);
        expect(
            nextBatch(
                marks.filter((m) => m.employeeId === 'beto'),
                calm,
                0,
            ).map((m) => m.seq),
        ).toEqual([2, 4]);
    });

    it('goes one by one while it isolates a bad mark', () => {
        expect(
            nextBatch(many(5), { ...calm, isolating: 5 }, 0).map((m) => m.seq),
        ).toEqual([1]);
    });

    it('does not carry what the server refused', () => {
        const marks = [keep(mark(1), 'wrong_center'), mark(2)];

        expect(nextBatch(marks, calm, 0).map((m) => m.seq)).toEqual([2]);
        expect(nextBatch([keep(mark(1), 'x')], calm, 0)).toEqual([]);
    });
});

describe('body and postInit', () => {
    it('is the items of the contract, as the fixture has them', () => {
        const batch = fixture.request.items.map((item) =>
            mark(item.seq, 'ana', item),
        );

        expect(body(batch)).toEqual(fixture.request);
    });

    it('sends JSON without cookies', () => {
        const init = postInit({ a: 1 });

        expect(init).toEqual({
            method: 'POST',
            headers: JSON_HEADERS,
            credentials: 'omit',
            body: '{"a":1}',
        });
        expect(JSON_HEADERS).toEqual({
            Accept: 'application/json',
            'Content-Type': 'application/json',
        });
    });
});

describe('outcomeOf', () => {
    it('reads the answer of the fixture: its results, tickets and context', () => {
        const outcome = outcomeOf(answer(200, fixture.response), 8);

        expect(outcome.kind).toBe('applied');

        if (outcome.kind !== 'applied') {
            return;
        }

        expect(outcome.results).toHaveLength(8);
        expect(outcome.tickets).toHaveLength(2);
        expect(outcome.context?.rpId).toBe('humetria.test');
    });

    it('applies each result: some leave, some stay with their reason', () => {
        const outcome = outcomeOf(answer(200, fixture.response), 8);
        const marks = [0, 1, 2, 3, 4, 5, 6, 7].map((seq) => mark(seq));

        if (outcome.kind !== 'applied') {
            throw new Error('Not applied.');
        }

        const applied = apply(marks, outcome.results);

        expect(applied.removed).toEqual([0, 1, 2]);
        expect(applied.puts.map((m) => [m.seq, m.reason])).toEqual([
            [3, 'sequence_conflict'],
            [4, 'wrong_center'],
            [5, 'ticket_invalid'],
            [6, 'signature_invalid'],
            [7, 'ticket_reused'],
        ]);
    });

    it('answers 200 without tickets or context when no signature verified', () => {
        const outcome = outcomeOf(
            answer(200, {
                results: [{ seq: 1, status: 'rejected', reason: 'x' }],
            }),
            1,
        );

        expect(outcome).toEqual({
            kind: 'applied',
            results: [{ seq: 1, status: 'rejected', reason: 'x' }],
            tickets: [],
            context: null,
        });
    });

    it('retries a 200 that has no results to read', () => {
        expect(outcomeOf(answer(200, { results: 'none' }), 2)).toEqual({
            kind: 'retry',
            retryAfter: null,
        });
        expect(outcomeOf(answer(200), 2).kind).toBe('retry');
    });

    it('splits a batch when the envelope is refused, 422 or 413, and refuses a lone mark as malformed', () => {
        for (const { status, body: refusal } of fixture.envelope.filter(
            ({ status: code }) => code === 413 || code === 422,
        )) {
            expect(outcomeOf(answer(status, refusal), 5)).toEqual({
                kind: 'split',
                size: 5,
            });
            expect(outcomeOf(answer(status, refusal), 1)).toEqual({
                kind: 'malformed',
            });
        }
    });

    it('retries the whole batch, deleting nothing, on 403, 404, 429, 5xx or no network', () => {
        for (const status of [403, 404, 500, 503, null]) {
            expect(outcomeOf(answer(status), 3)).toEqual({
                kind: 'retry',
                retryAfter: null,
            });
        }

        expect(outcomeOf(answer(429, null, 120), 3)).toEqual({
            kind: 'retry',
            retryAfter: 120,
        });
    });
});

describe('isNetworkFailure', () => {
    it('knows a fetch that never got an answer', () => {
        expect(isNetworkFailure(new TypeError('Failed to fetch'))).toBe(true);
        expect(
            isNetworkFailure(new DOMException('timed out', 'TimeoutError')),
        ).toBe(true);
        expect(
            isNetworkFailure(new DOMException('aborted', 'AbortError')),
        ).toBe(true);
    });

    it('does not take any other error for the network', () => {
        expect(isNetworkFailure(new Error('boom'))).toBe(false);
        expect(
            isNetworkFailure(new DOMException('no', 'NotAllowedError')),
        ).toBe(false);
        expect(isNetworkFailure('Failed to fetch')).toBe(false);
        expect(isNetworkFailure(null)).toBe(false);
    });
});
