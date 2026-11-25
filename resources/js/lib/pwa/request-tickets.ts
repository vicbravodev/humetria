import { absorb } from '@/lib/pwa/absorb';
import type { OfflineStore } from '@/lib/pwa/offline-store';
import { queueReport } from '@/lib/pwa/queue';
import type { Answer } from '@/lib/pwa/run-delivery';
import { newest, renewalDue } from '@/lib/pwa/tickets';

/**
 * Swaps the newest ticket the phone holds for a new batch (`POST /me/{slug}/tickets`, no session: the ticket is the
 * credential), never before 30 seconds since the last time. With no ticket it asks nothing: the first ones come with a
 * signed action. It tells the server how many marks of the person wait. A «no» or no answer deletes nothing.
 */
export async function requestTickets(
    store: OfflineStore,
    deps: {
        post: (payload: unknown) => Promise<Answer>;
        now: () => number;
    },
): Promise<void> {
    const held = newest(await store.tickets());
    const now = deps.now();

    if (held === null || !renewalDue(await store.ticketsAt(), now)) {
        return;
    }

    await store.saveTicketsAt(now);

    const context = await store.context();
    const queue =
        context === null
            ? null
            : queueReport(await store.marks(), context.employeeId, now);
    const answer = await deps.post({
        ticket: held.ticket,
        ...(queue === null ? {} : { queue }),
    });

    if (answer.status === 200) {
        await absorb(store, answer.json);
    }
}
