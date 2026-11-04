/*
 * A calendar date is a day on the wall calendar, written Y-m-d as the server sends and expects it ("2026-11-30").
 * It is read and written in local time so the day never shifts: the server already did the timezone math.
 */

const pattern = /^\d{4}-\d{2}-\d{2}$/;

export function fromCalendarDate(text: string): Date | undefined {
    if (!pattern.test(text)) {
        return undefined;
    }

    // A date-time without an offset is read in local time; 2026-02-30 comes out invalid or rolled over, never the same.
    const date = new Date(`${text}T00:00`);

    return toCalendarDate(date) === text ? date : undefined;
}

export function toCalendarDate(date: Date): string {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}

const longDate = new Intl.DateTimeFormat('es-MX', { dateStyle: 'long' });

/** "30 de noviembre de 2026", or nothing when the text is not a date. */
export function describeDate(text: string): string | undefined {
    const date = fromCalendarDate(text);

    return date === undefined ? undefined : longDate.format(date);
}

/** What a calendar closes: the days before `min` and after `max` (Y-m-d); nothing when neither is a date. */
export function pickableDays(
    min: string | undefined,
    max: string | undefined,
): Array<{ before: Date } | { after: Date }> | undefined {
    const first = min === undefined ? undefined : fromCalendarDate(min);
    const last = max === undefined ? undefined : fromCalendarDate(max);
    const closed = [
        ...(first === undefined ? [] : [{ before: first }]),
        ...(last === undefined ? [] : [{ after: last }]),
    ];

    return closed.length === 0 ? undefined : closed;
}

/** What a calendar needs to respect a first and a last day: where it starts, and the days it closes. */
export function calendarLimits(
    min: string | undefined,
    max: string | undefined,
): { startMonth?: Date; disabled?: Array<{ before: Date } | { after: Date }> } {
    const first = min === undefined ? undefined : fromCalendarDate(min);
    const disabled = pickableDays(min, max);

    return {
        ...(first === undefined ? {} : { startMonth: first }),
        ...(disabled === undefined ? {} : { disabled }),
    };
}
