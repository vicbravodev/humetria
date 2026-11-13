import { useEffect, useRef, useState } from 'react';
import type { Option, SearchState } from '@/lib/option-search';
import { createRemoteSearch } from '@/lib/remote-search';
import { loadServerOptions } from '@/lib/server-options';
import { options as peopleOptions } from '@/routes/tenant/org-chart/people';

const IDLE: SearchState = { status: 'idle', options: [] };

/**
 * The people of the company whose name or number has what was typed in ⌘K: from two letters, after 200 ms of quiet
 * typing, cancelling the request a newer text makes stale. `enabled` is false for whoever may not manage the
 * organization: then nothing is asked.
 */
export function usePalettePeople(
    query: string,
    tenantSlug: string | null,
    enabled: boolean,
): Option[] {
    const [state, setState] = useState(IDLE);
    const search = useRef<ReturnType<typeof createRemoteSearch> | null>(null);
    const on = enabled && tenantSlug !== null;

    useEffect(() => {
        if (!on || tenantSlug === null) {
            return undefined;
        }

        const searcher = createRemoteSearch({
            load: (text, signal) =>
                loadServerOptions(peopleOptions.url(tenantSlug), text, signal),
            onChange: setState,
            delay: 200,
            minLength: 2,
        });
        search.current = searcher;

        return searcher.cancel;
    }, [on, tenantSlug]);

    useEffect(() => {
        search.current?.search(query);
    }, [query]);

    return on ? state.options : [];
}
