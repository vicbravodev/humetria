/*
 * The hour of a mark made without a connection is the one the person says, on their phone's wall clock (never the server's:
 * there is no server). The server bounds it with what it can prove, so this only turns «08:02» into the instant the phone
 * signs and sends, in unix seconds.
 */

const DAY_MS = 86_400_000;

/** The whole seconds of an instant. */
export function secondsOf(instant: Date): number {
    return Math.floor(instant.getTime() / 1000);
}

/**
 * The instant of an hour written as HH:mm, today on the phone; yesterday when that hour has not come yet (marking at 00:30
 * for a shift that ended at 23:50). Null when the text is not an hour.
 */
export function declaredFromTime(now: Date, time: string): number | null {
    const match = /^([01]\d|2[0-3]):([0-5]\d)$/.exec(time);

    if (match === null) {
        return null;
    }

    const today = new Date(now);
    today.setHours(Number(match[1]), Number(match[2]), 0, 0);

    return secondsOf(
        today.getTime() > now.getTime()
            ? new Date(today.getTime() - DAY_MS)
            : today,
    );
}

/** Unix seconds as the phone's HH:mm: what «Usted indicó 08:02» says. */
export function clockOf(seconds: number): string {
    const instant = new Date(seconds * 1000);
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${pad(instant.getHours())}:${pad(instant.getMinutes())}`;
}
