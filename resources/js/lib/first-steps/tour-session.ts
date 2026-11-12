import type { FirstStepKey } from '@/types/first-steps';

const KEY = 'humetria:first-steps-tour';
const KEYS: readonly string[] = [
    'work_centers',
    'people',
    'evaluation',
    'team',
    'humi',
];

function isStepKey(value: string | null): value is FirstStepKey {
    return value !== null && KEYS.includes(value);
}

/** Remembers which tour to run once the next page is open. */
export function rememberTour(key: FirstStepKey): void {
    try {
        window.sessionStorage.setItem(KEY, key);
    } catch {
        // Storage can be blocked; the tour is only a convenience.
    }
}

/** Reads and clears the remembered tour. */
export function takeTour(): FirstStepKey | null {
    try {
        const value = window.sessionStorage.getItem(KEY);
        window.sessionStorage.removeItem(KEY);

        return isStepKey(value) ? value : null;
    } catch {
        return null;
    }
}
