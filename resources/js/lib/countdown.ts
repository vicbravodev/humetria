/** The seconds left before something can be done again, as a clock: 42 → "0:42". */
export function formatCountdown(seconds: number): string {
    const left = Math.max(0, seconds);

    return `${Math.floor(left / 60)}:${String(left % 60).padStart(2, '0')}`;
}
