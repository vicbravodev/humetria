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

/** Remembers which tour to run once the next page of that company is open. */
export function rememberTour(key: FirstStepKey, company: string): void {
    try {
        window.sessionStorage.setItem(KEY, `${company}:${key}`);
    } catch {
        // Storage can be blocked; the tour is only a convenience.
    }
}

/** Reads and clears the remembered tour; it is only taken in the company it was remembered for. */
export function takeTour(company: string): FirstStepKey | null {
    try {
        const value = window.sessionStorage.getItem(KEY);
        window.sessionStorage.removeItem(KEY);
        const split = value?.lastIndexOf(':') ?? -1;
        if (value === null || split < 0 || value.slice(0, split) !== company) {
            return null;
        }
        const key = value.slice(split + 1);

        return isStepKey(key) ? key : null;
    } catch {
        return null;
    }
}
