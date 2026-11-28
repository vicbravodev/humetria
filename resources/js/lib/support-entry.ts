/** What the support strip says of the time left, in whole minutes, a started minute counting as one. */
export function timeLeftText(seconds: number): string {
    if (seconds <= 0) {
        return 'Tiempo terminado';
    }

    const minutes = Math.ceil(seconds / 60);

    return minutes === 1 ? 'Queda 1 min' : `Quedan ${minutes} min`;
}

/** How long an entry as support lasts: what the server accepts. */
export const SUPPORT_MINUTES = [
    { value: 30, label: '30 min' },
    { value: 60, label: '1 h' },
    { value: 120, label: '2 h' },
];
