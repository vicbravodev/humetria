import { useEffect, useRef, useState } from 'react';
import type { Option, SearchState } from '@/lib/option-search';
import { createRemoteSearch } from '@/lib/remote-search';
import { loadServerOptions } from '@/lib/server-options';

const IDLE: SearchState = { status: 'idle', options: [] };

/**
 * What the server finds for the text typed in ⌘K at `url`: from two letters, after 200 ms of quiet typing, cancelling the
 * request a newer text makes stale. A null `url` asks nothing: the role may not search there.
 */
export function usePaletteOptions(url: string | null, query: string): Option[] {
    const [state, setState] = useState(IDLE);
    const search = useRef<ReturnType<typeof createRemoteSearch> | null>(null);

    useEffect(() => {
        if (url === null) {
            return undefined;
        }

        const searcher = createRemoteSearch({
            load: (text, signal) => loadServerOptions(url, text, signal),
            onChange: setState,
            delay: 200,
            minLength: 2,
        });
        search.current = searcher;

        return searcher.cancel;
    }, [url]);

    useEffect(() => {
        search.current?.search(query);
    }, [query]);

    return url === null ? [] : state.options;
}
