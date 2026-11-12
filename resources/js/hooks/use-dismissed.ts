import { useState } from 'react';

function readDismissed(key: string): boolean {
    try {
        return window.localStorage.getItem(key) === '1';
    } catch {
        return false;
    }
}

/**
 * Whether a person already closed a note that explains a screen, kept in this browser only: a private window or
 * blocked storage just shows the note again.
 */
export function useDismissed(key: string): readonly [boolean, () => void] {
    const [dismissed, setDismissed] = useState(() => readDismissed(key));

    return [
        dismissed,
        () => {
            setDismissed(true);

            try {
                window.localStorage.setItem(key, '1');
            } catch {
                // Nothing to remember it with: it comes back next time.
            }
        },
    ];
}
