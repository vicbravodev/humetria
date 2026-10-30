import { router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { debounce } from '@/lib/debounce';

/**
 * A list's filters, each one a visit to the same page with the new query. The text waits for typing to pause
 * (300 ms) so the server is asked once per search, not once per key.
 */
export function useQueryFilters<F extends { q: string }>(
    url: string,
    filters: F,
    toQuery: (filters: F) => Partial<Record<keyof F, string>>,
) {
    const [text, setText] = useState(filters.q);
    const search = useMemo(() => {
        const visit = (next: Partial<F>) =>
            router.get(url, toQuery({ ...filters, ...next }), {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });

        return {
            visit,
            typed: debounce((q: string) => visit({ ...filters, q }), 300),
        };
    }, [url, filters, toQuery]);

    useEffect(() => search.typed.cancel, [search]);

    return {
        text,
        type: (q: string) => {
            setText(q);
            search.typed.call(q);
        },
        filter: (next: Partial<F>) => search.visit(next),
    };
}
