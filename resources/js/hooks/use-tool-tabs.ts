import { useEffect, type KeyboardEvent } from 'react';
import { nextTab } from '@/lib/tabs';

export function useToolTabs<Key extends string>(
    keys: readonly Key[],
    selected: Key,
    onSelect: (key: Key) => void,
) {
    useEffect(() => {
        const tab = document.getElementById(`tool-tab-${selected}`);

        if (tab === null) {
            return;
        }

        const strip = tab.parentElement?.getBoundingClientRect();
        const item = tab.getBoundingClientRect();

        if (
            strip === undefined ||
            (item.left >= strip.left &&
                item.right <= strip.right &&
                item.top >= strip.top &&
                item.bottom <= strip.bottom)
        ) {
            return;
        }

        tab.scrollIntoView({
            behavior: 'instant',
            block: 'nearest',
            inline: 'nearest',
        });
    }, [selected]);

    return (event: KeyboardEvent<HTMLElement>, index: number): void => {
        const next = nextTab(event.key, index, keys.length);
        const key = next === null ? undefined : keys[next];

        if (key === undefined) {
            return;
        }

        event.preventDefault();
        onSelect(key);
        document
            .getElementById(`tool-tab-${key}`)
            ?.focus({ preventScroll: true });
    };
}
