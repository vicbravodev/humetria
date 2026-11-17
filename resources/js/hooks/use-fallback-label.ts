import { useEffect } from 'react';
import type { RefObject } from 'react';

/**
 * Names a field that no label points at (some rows show their title beside it, not as a <label>): it takes `label` as
 * its aria-label. A field with a label of its own keeps that one.
 */
export function useFallbackLabel(
    ref: RefObject<HTMLInputElement | null>,
    label: string,
): void {
    useEffect(() => {
        const input = ref.current;

        if (
            input !== null &&
            (input.labels?.length ?? 0) === 0 &&
            !input.hasAttribute('aria-labelledby')
        ) {
            input.setAttribute('aria-label', label);
        }
    }, [ref, label]);
}
