import { router } from '@inertiajs/react';
import type { RefObject } from 'react';
import { useEffect, useRef, useState } from 'react';
import { debounce } from '@/lib/debounce';

type Latest<F> = {
    url: string;
    filters: F;
    toQuery: (filters: F) => Partial<Record<keyof F, string>>;
};

/** The visits of a list, built once: each reads the filters the server answered last. */
function querySearch<F extends { q: string }>(latest: RefObject<Latest<F>>) {
    const visit = (next: Partial<F>) => {
        const current = latest.current;

        router.get(
            current.url,
            current.toQuery({ ...current.filters, ...next }),
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return {
        visit,
        typed: debounce(
            (q: string) => visit({ ...latest.current.filters, q }),
            300,
        ),
    };
}

/**
 * A list's filters, each one a visit to the same page with the new query. The text waits for typing to pause
 * (300 ms) so the server is asked once per search, not once per key.
 *
 * The waiting search lives as long as the list, and reads the filters the server answered last, so an answer that
 * arrives mid-typing neither drops nor undoes letters. Any other visit (a status tab, a page) drops the search still
 * waiting, which would otherwise undo it, and puts back the text actually searched.
 */
export function useQueryFilters<F extends { q: string }>(
    url: string,
    filters: F,
    toQuery: (filters: F) => Partial<Record<keyof F, string>>,
) {
    const [text, setText] = useState(filters.q);
    const latest = useRef<Latest<F>>({ url, filters, toQuery });

    useEffect(() => {
        latest.current = { url, filters, toQuery };
    });

    const search = useRef<ReturnType<typeof querySearch<F>> | null>(null);
    // Built on first use, in a handler: the same one for as long as the list is open.
    const searchOf = () => (search.current ??= querySearch(latest));

    useEffect(() => {
        const stop = router.on('before', (event) => {
            const visit = event.detail.visit;

            if (
                !visit.prefetch &&
                !visit.async &&
                search.current?.typed.cancel()
            ) {
                setText(latest.current.filters.q);
            }
        });

        return () => {
            stop();
            search.current?.typed.cancel();
        };
    }, []);

    return {
        text,
        type: (q: string) => {
            setText(q);
            searchOf().typed.call(q);
        },
        filter: (next: Partial<F>) => {
            // The text typed so far goes along, so the filter does not drop it.
            searchOf().typed.cancel();
            searchOf().visit({ ...latest.current.filters, q: text, ...next });
        },
    };
}
