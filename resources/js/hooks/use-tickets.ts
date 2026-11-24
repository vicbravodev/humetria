import { useCallback, useEffect, useState } from 'react';
import {
    announceStoreChange,
    useOfflineStore,
    useStoreChanged,
} from '@/hooks/use-offline-store';
import { useWhenBack } from '@/hooks/use-when-back';
import type { Practice } from '@/lib/pwa/offline-store';
import { postJson } from '@/lib/pwa/post';
import { requestTickets } from '@/lib/pwa/request-tickets';
import { stock } from '@/lib/pwa/tickets';
import type { OfflineContext, Pass, Ticket } from '@/lib/pwa/tickets';

/** How long a request for tickets may take. */
const REQUEST_TIMEOUT_MS = 10_000;

type Held = {
    tickets: Ticket[];
    context: OfflineContext | null;
    pass: Pass | null;
    practice: Practice | null;
};

const NOTHING: Held = {
    tickets: [],
    context: null,
    pass: null,
    practice: null,
};

/**
 * What the phone holds to mark without a connection (tickets, context, pass, practice) and the way to keep the tickets
 * fresh: it swaps one for a new batch on opening, when the network returns and when the app comes to the front, never
 * sooner than 30 seconds after the last time. Without a session: the ticket is the credential.
 */
export function useTickets({
    slug,
    ticketsUrl,
}: {
    slug: string;
    ticketsUrl: string;
}) {
    const store = useOfflineStore(slug);
    const [held, setHeld] = useState<Held>(NOTHING);
    const [version, setVersion] = useState(0);

    useEffect(() => {
        if (store === null) {
            return undefined;
        }

        let alive = true;

        void Promise.all([
            store.tickets(),
            store.context(),
            store.pass(),
            store.practice(),
        ]).then(([tickets, context, pass, practice]) => {
            if (alive) {
                setHeld({ tickets, context, pass, practice });
            }
        });

        return () => {
            alive = false;
        };
    }, [store, version]);

    useStoreChanged(useCallback(() => setVersion((n) => n + 1), []));

    const refresh = useCallback(async () => {
        if (store !== null) {
            await requestTickets(store, {
                post: (payload) =>
                    postJson(ticketsUrl, payload, REQUEST_TIMEOUT_MS),
                now: Date.now,
            });
            announceStoreChange();
        }
    }, [store, ticketsUrl]);

    useEffect(() => {
        void refresh();
    }, [refresh]);
    useWhenBack(refresh);

    return {
        stock: stock(held.tickets),
        count: held.tickets.length,
        context: held.context,
        pass: held.pass,
        practice: held.practice,
        refresh,
    };
}
