const MINUTE = 60_000;

/** A span of minutes in words: «32 min», «1 h», «3 h 41 min»; never negative. */
export function spanText(minutes: number): string {
    const total = Math.max(0, Math.floor(minutes));
    const hours = Math.floor(total / 60);
    const rest = total % 60;

    if (hours === 0) {
        return `${rest} min`;
    }

    return rest === 0 ? `${hours} h` : `${hours} h ${rest} min`;
}

type Timed = { type: string; time: string; at: number };

/** The minutes between marks that count as work: from a start or a return to the next break or the end (or `until`). */
function workedMinutes(marks: readonly Timed[], until: number): number {
    let opened: number | null = null;
    let worked = 0;

    for (const mark of marks) {
        if (mark.type === 'start' || mark.type === 'break_end') {
            opened = mark.at;
        } else if (opened !== null) {
            worked += mark.at - opened;
            opened = null;
        }
    }

    return (worked + (opened === null ? 0 : until - opened)) / MINUTE;
}

/**
 * The clock of the person's day (board «Inicio»): while they work, how long since the start or the return from the break;
 * on a break, how long it has lasted; once the day ended, what they worked net of the breaks. Nothing before the first mark.
 * `now` is the server's time.
 */
export function dayTimer(
    marks: readonly Timed[],
    now: number,
): { value: string; label: string } | null {
    const last = marks.at(-1);

    if (last === undefined) {
        return null;
    }

    switch (last.type) {
        case 'start':
            return {
                value: spanText((now - last.at) / MINUTE),
                label: 'de jornada',
            };
        case 'break_start':
            return {
                value: spanText((now - last.at) / MINUTE),
                label: 'de comida',
            };
        case 'break_end':
            return {
                value: spanText((now - last.at) / MINUTE),
                label: 'desde su regreso',
            };
        default:
            return {
                value: spanText(workedMinutes(marks, last.at)),
                label: 'trabajadas hoy',
            };
    }
}
