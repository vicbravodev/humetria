import { useState } from 'react';

/**
 * A field's value that the field keeps itself, unless the page holds it: then the page's value shows and every change
 * is told to it.
 */
export function useHeldValue(
    initial: string,
    held: string | undefined,
    onChange: ((value: string) => void) | undefined,
): [string, (value: string) => void] {
    const [own, setOwn] = useState(initial);

    return [
        held ?? own,
        (value) => {
            setOwn(value);
            onChange?.(value);
        },
    ];
}
