import type { OfflineStore } from '@/lib/pwa/offline-store';
import { contextIn, merge, ticketsIn } from '@/lib/pwa/tickets';

/**
 * Keeps what a signed answer of the server (or the answer to a request for tickets) carries: its tickets, joined to those
 * held, and its context. When the context names another person than the one held, what was held (tickets, pass, device key,
 * practice) was the first person's and goes first; the marks that wait stay. True when the answer carried something.
 */
export async function absorb(
    store: OfflineStore,
    json: unknown,
): Promise<boolean> {
    const incoming = ticketsIn(json);
    const context = contextIn(json);

    if (incoming.length === 0 && context === null) {
        return false;
    }

    const held = await store.context();

    if (
        context !== null &&
        held !== null &&
        held.employeeId !== context.employeeId
    ) {
        await store.forgetPerson();
    }

    if (incoming.length > 0) {
        await store.saveTickets(merge(await store.tickets(), incoming));
    }

    if (context !== null) {
        await store.saveContext(context);
    }

    return true;
}
