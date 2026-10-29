import { useDeferredValue, useEffect, useMemo, useRef, useState } from 'react';
import type { Option, SearchState } from '@/lib/option-search';
import { filterOptions, indexOptions, searchNote } from '@/lib/option-search';
import { createRemoteSearch } from '@/lib/remote-search';

/** At most this many options on screen: a long list is narrowed by typing, not by scrolling. */
const LIMIT = 50;

/**
 * Where a Combobox's options come from. `options`: all of them are on the page and are filtered here.
 * `load`: they live on the server; `initial` (a few) shows until `minLength` letters are typed, then the
 * server is asked after `delay` ms of quiet typing.
 */
export type OptionSource =
    | { options: Option[] }
    | {
          initial: Option[];
          load: (query: string, signal: AbortSignal) => Promise<Option[]>;
          minLength?: number;
          delay?: number;
      };

const IDLE: SearchState = { status: 'idle', options: [] };

function useRemoteSearch(source: OptionSource, query: string): SearchState {
    const [state, setState] = useState(IDLE);
    const remote = 'load' in source ? source : null;
    const load = useRef(remote?.load);
    const search = useRef<ReturnType<typeof createRemoteSearch> | null>(null);
    const delay = remote?.delay ?? 250;
    const minLength = remote?.minLength ?? 2;
    const enabled = remote !== null;

    useEffect(() => {
        load.current = remote?.load;
    });

    useEffect(() => {
        if (!enabled) {
            return undefined;
        }

        const searcher = createRemoteSearch({
            load: (text, signal) =>
                load.current?.(text, signal) ?? Promise.resolve([]),
            onChange: setState,
            delay,
            minLength,
        });
        search.current = searcher;

        return searcher.cancel;
    }, [enabled, delay, minLength]);

    useEffect(() => {
        search.current?.search(query);
    }, [query]);

    return state;
}

/**
 * The options to show for what was typed, with the note under them. Local options are filtered off the
 * keystroke's critical path (useDeferredValue); server ones are debounced, cancelled when stale and cached.
 */
export function useOptionSearch(source: OptionSource, query: string) {
    const deferred = useDeferredValue(query);
    const listed = 'options' in source ? source.options : source.initial;
    const index = useMemo(() => indexOptions(listed), [listed]);
    const remote = useRemoteSearch(source, query);
    const minLength = 'load' in source ? (source.minLength ?? 2) : 0;
    const local = filterOptions(index, deferred, LIMIT);
    const fromServer = 'load' in source && remote.status !== 'idle';
    const status = 'load' in source ? remote.status : 'done';
    const options = fromServer ? remote.options : local.options;
    const more = !fromServer && local.more;

    return {
        options,
        note: searchNote({ status, found: options.length, more, minLength }),
    };
}
