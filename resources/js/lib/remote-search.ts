import { fold } from '@/lib/commands';
import type { Option, SearchState } from '@/lib/option-search';

type RemoteSearchOptions = {
    load: (query: string, signal: AbortSignal) => Promise<Option[]>;
    onChange: (state: SearchState) => void;
    /** Milliseconds of quiet typing before asking the server. */
    delay: number;
    /** Letters needed before asking at all; below it the search is idle. */
    minLength: number;
    /** How many answered texts it remembers; the oldest is forgotten first. */
    cacheSize?: number;
};

/**
 * Searching options on the server as a person types: it waits for a pause (debounce), asks once for the last
 * text, aborts the request a newer text makes stale and ignores its answer, keeps the previous results on
 * screen while loading, and answers a text it already asked for from memory without asking again.
 */
export function createRemoteSearch({
    load,
    onChange,
    delay,
    minLength,
    cacheSize = 50,
}: RemoteSearchOptions) {
    const cache = new Map<string, Option[]>();
    let timer: ReturnType<typeof setTimeout> | undefined;
    let controller: AbortController | undefined;
    let shown: Option[] = [];

    const report = (state: SearchState) => {
        shown = state.options;
        onChange(state);
    };

    const remember = (key: string, options: Option[]) => {
        cache.set(key, options);

        if (cache.size > cacheSize) {
            cache.delete(cache.keys().next().value ?? key);
        }
    };

    const ask = async (key: string, query: string) => {
        const current = new AbortController();
        controller = current;
        report({ status: 'loading', options: shown });

        try {
            const options = await load(query, current.signal);

            if (!current.signal.aborted) {
                remember(key, options);
                report({ status: 'done', options });
            }
        } catch {
            if (!current.signal.aborted) {
                report({ status: 'error', options: [] });
            }
        }
    };

    const cancel = () => {
        clearTimeout(timer);
        controller?.abort();
    };

    const search = (query: string) => {
        cancel();
        const key = fold(query);
        const remembered = cache.get(key);

        if (key.length < minLength) {
            report({ status: 'idle', options: [] });
        } else if (remembered !== undefined) {
            report({ status: 'done', options: remembered });
        } else {
            timer = setTimeout(() => void ask(key, query.trim()), delay);
        }
    };

    return { search, cancel };
}
