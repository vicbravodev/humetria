import type { KeyboardEvent } from 'react';
import { useEffect, useState } from 'react';
import type { OptionSource } from '@/hooks/use-option-search';
import { useOptionSearch } from '@/hooks/use-option-search';
import { moveActive } from '@/lib/commands';
import type { Option } from '@/lib/option-search';

/**
 * The state of a Combobox's search: what was typed, the options it finds, the one the arrows point at
 * (kept in view) and the keys that move it or pick it.
 */
export function useCombobox(
    id: string,
    source: OptionSource,
    onPick: (option: Option) => void,
) {
    const [query, setQuery] = useState('');
    const [active, setActive] = useState(0);
    const { options, note } = useOptionSearch(source, query);
    const listId = `${id}-options`;
    const activeId =
        options[active] === undefined ? undefined : `${listId}-${active}`;

    useEffect(() => {
        if (activeId !== undefined) {
            document
                .getElementById(activeId)
                ?.scrollIntoView({ block: 'nearest' });
        }
    }, [activeId]);

    // Picking starts the next search from scratch; an added option is from then on just its label.
    const pick = (option: Option) => {
        setQuery('');
        setActive(0);
        onPick({ value: option.value, label: option.label });
    };

    const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        const option = options[active];
        const next = moveActive(event.key, active, options.length);

        if (event.key === 'Enter' || next !== undefined) {
            event.preventDefault();
        }

        if (event.key === 'Enter' && option !== undefined) {
            pick(option);
        } else if (next !== undefined) {
            setActive(next);
        }
    };

    const type = (text: string) => {
        setQuery(text);
        setActive(0);
    };

    return {
        query,
        type,
        options,
        note,
        active,
        setActive,
        listId,
        activeId,
        onKeyDown,
        pick,
    };
}
