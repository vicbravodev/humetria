import { useEffect, useState } from 'react';

/**
 * The tab chosen among `keys`, kept in the address as `#{prefix}{key}` so a link can open on one. It opens on `first`
 * until the address says otherwise; picking a tab rewrites the address without scrolling.
 */
export function useHashTab<Key extends string>(
    first: Key,
    keys: readonly Key[],
    prefix: string,
): readonly [Key, (key: Key) => void] {
    const [selected, setSelected] = useState<Key>(first);

    useEffect(() => {
        const sync = (): void => {
            const named = keys.find(
                (key) => `#${prefix}${key}` === window.location.hash,
            );

            if (named !== undefined) {
                setSelected(named);
            }
        };

        sync();
        window.addEventListener('hashchange', sync);

        return () => window.removeEventListener('hashchange', sync);
    }, [keys, prefix]);

    const select = (key: Key): void => {
        setSelected(key);
        window.history.replaceState(null, '', `#${prefix}${key}`);
    };

    return [selected, select];
}
