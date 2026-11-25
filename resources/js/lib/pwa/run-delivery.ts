import { absorb } from '@/lib/pwa/absorb';
import { body, nextBatch, outcomeOf, scheduleAfter } from '@/lib/pwa/delivery';
import type { Outcome } from '@/lib/pwa/delivery';
import type { OfflineStore } from '@/lib/pwa/offline-store';
import { apply, keep, pending } from '@/lib/pwa/queue';
import type { QueueNotice, QueuedMark } from '@/lib/pwa/queue';

/** What the server answered (or `status` null when nothing came back), already read; `retryAfter` in seconds. */
export type Answer = {
    status: number | null;
    json: unknown;
    retryAfter: number | null;
};

export type DeliveryDeps = {
    post: (payload: ReturnType<typeof body>) => Promise<Answer>;
    now: () => number;
};

export type Delivered = {
    notices: QueueNotice[];
    /** When to try again, if marks still wait; null when none do. */
    nextAt: number | null;
};

/** What one attempt did to the queue, and the outcome to schedule by. */
async function settle(
    store: OfflineStore,
    batch: QueuedMark[],
    answer: Answer,
): Promise<{ outcome: Outcome; notices: QueueNotice[] }> {
    const outcome = outcomeOf(answer, batch.length);

    if (outcome.kind === 'malformed') {
        const [only] = batch;

        if (only !== undefined) {
            await store.settle({
                puts: [keep(only, 'malformed')],
                removed: [],
                notices: [],
            });
        }

        return { outcome, notices: [] };
    }

    if (outcome.kind !== 'applied') {
        return { outcome, notices: [] };
    }

    const applied = apply(batch, outcome.results);

    if (applied.puts.length + applied.removed.length === 0) {
        // The server said nothing about these marks: the same as not having answered, or this would never end.
        return { outcome: { kind: 'retry', retryAfter: null }, notices: [] };
    }

    await store.settle(applied);
    await absorb(store, answer.json);

    return { outcome, notices: applied.notices };
}

/**
 * Delivers the queue: batch after batch, one person at a time, until nothing waits or an attempt did not go through (then
 * it says when to try again). Each answer is applied to the marks, and the tickets and context it brings are kept. The
 * request itself (`post`) and the clock are given, so the planner is the pure code of delivery.ts.
 */
export async function runDelivery(
    store: OfflineStore,
    deps: DeliveryDeps,
): Promise<Delivered> {
    const notices: QueueNotice[] = [];

    for (;;) {
        const marks = await store.marks();
        const schedule = await store.schedule();
        const batch = nextBatch(marks, schedule, deps.now());

        if (batch.length === 0) {
            break;
        }

        const settled = await settle(
            store,
            batch,
            await deps.post(body(batch)),
        );
        notices.push(...settled.notices);
        await store.saveSchedule(
            scheduleAfter(schedule, settled.outcome, {
                size: batch.length,
                now: deps.now(),
            }),
        );

        if (settled.outcome.kind === 'retry') {
            break;
        }
    }

    const waiting = pending(await store.marks());

    return {
        notices,
        nextAt:
            waiting.length === 0
                ? null
                : (await store.schedule()).nextAttemptAt,
    };
}
