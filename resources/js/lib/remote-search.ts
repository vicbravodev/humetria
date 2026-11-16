import { fold } from '@/lib/commands';
import type { Option, SearchState } from '@/lib/option-search';
import { filterOptions, indexOptions } from '@/lib/option-search';

type RemoteSearchOptions = {
    load: (query: string, signal: AbortSignal) => Promise<Option[]>;
    onChange: (state: SearchState) => void;
    /** Milliseconds of quiet typing before asking the server. */
    delay: number;
    /** Letters needed before asking at all; below it the search is idle. */
    minLength: number;
    /** How many answered texts it remembers; the oldest is forgotten first. */
    cacheSize?: number;
    /** The server's page size: an answer to the empty text shorter than this is the whole list. */
    pageSize?: number;
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
    pageSize = 20,
}: RemoteSearchOptions) {
    const cache = new Map<string, Option[]>();
    let timer: ReturnType<typeof setTimeout> | undefined;
    let controller: AbortController | undefined;
    let shown: Option[] = [];
    let everything: ReturnType<typeof indexOptions> | undefined;
    // The first page (the empty text) in flight, and the text typed meanwhile.
    let opening = false;
    let latest = '';

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
        opening = key === '';
        const current = new AbortController();
        controller = current;
        // What is still on screen is narrowed to what was typed, so Enter never picks what no longer matches.
        report({
            status: 'loading',
            options: filterOptions(indexOptions(shown), query, pageSize)
                .options,
        });

        try {
            const options = await load(query, current.signal);

            if (!current.signal.aborted) {
                remember(key, options);

                if (key === '' && options.length < pageSize) {
                    everything = indexOptions(options);
                }

                report({ status: 'done', options });
            }
        } catch {
            if (!current.signal.aborted) {
                report({ status: 'error', options: [] });
            }
        }

        if (opening) {
            opening = false;

            if (!current.signal.aborted && fold(latest) !== '') {
                search(latest);
            }
        }
    };

    const cancel = () => {
        clearTimeout(timer);
        controller?.abort();
    };

    function search(query: string) {
        latest = query;

        // The first page is on its way and is not aborted by typing (an aborted request costs the server as much as
        // an answered one): when it lands, what was typed is searched in it.
        if (opening) {
            return;
        }

        cancel();
        const key = fold(query);
        const remembered = cache.get(key);

        if (everything !== undefined) {
            // ponytail: the first page was the whole list, so typing only filters it here, no more requests.
            report({
                status: 'done',
                options: filterOptions(everything, query, pageSize).options,
            });
        } else if (key.length < minLength) {
            report({ status: 'idle', options: [] });
        } else if (remembered !== undefined) {
            report({ status: 'done', options: remembered });
        } else {
            // The empty text is the opening of the field, not typing: nothing to wait for.
            timer = setTimeout(
                () => void ask(key, query.trim()),
                key === '' ? 0 : delay,
            );
        }
    }

    return { search, cancel };
}
