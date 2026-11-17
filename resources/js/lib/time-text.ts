/*
 * What a person types in a time field, read as the 24-hour HH:mm the server takes: "7:30", "730", "7", "7:30 pm",
 * "12 am"… A time is a time of day: it never carries a date or a zone.
 */
import type { TextReading } from '@/lib/date-text';

const pad = (part: number): string => String(part).padStart(2, '0');

function clock(hours: number, minutes: number): string | undefined {
    return hours > 23 || minutes > 59
        ? undefined
        : `${pad(hours)}:${pad(minutes)}`;
}

/** The hour of a 12-hour clock: 12 am is midnight, 12 pm is noon; 0 or 13 and up are no hour of that clock. */
function twelveHour(hours: number, half: string): number | undefined {
    if (hours < 1 || hours > 12) {
        return undefined;
    }

    return (hours % 12) + (half === 'p' ? 12 : 0);
}

function readTime(text: string): string | undefined {
    const parts =
        /^(\d{1,2})(?: ?[:.] ?(\d{2}))? ?(?:([ap]) ?\.? ?m\.?)?$/.exec(text) ??
        /^(\d{1,2})(\d{2})()$/.exec(text);

    if (parts === null) {
        return undefined;
    }

    const minutes = Number(parts[2] ?? '0');
    const half = parts[3] ?? '';
    const hours =
        half === '' ? Number(parts[1]) : twelveHour(Number(parts[1]), half);

    return hours === undefined ? undefined : clock(hours, minutes);
}

/** Reads a typed or pasted time as HH:mm on a 24-hour clock. */
export function parseTimeText(text: string): TextReading {
    const tidied = text.toLowerCase().replace(/\s+/g, ' ').trim();

    if (tidied === '') {
        return { kind: 'empty' };
    }

    const value = readTime(tidied);

    return value === undefined ? { kind: 'invalid' } : { kind: 'ok', value };
}

/** Adds the colon after the hour while it is typed in digits, and writes four pasted digits as a time. */
export function maskTimeText(next: string, previous: string): string {
    if (/^\d{4}$/.test(next)) {
        return `${next.slice(0, 2)}:${next.slice(2)}`;
    }

    return next.length > previous.length && /^\d{2}$/.test(next)
        ? `${next}:`
        : next;
}

/** The time some minutes later (or earlier), around the clock; a field without a time starts at 08:00. */
export function stepTime(value: string, minutes: number): string {
    const parts = /^(\d{2}):(\d{2})$/.exec(value);
    const start =
        parts === null ? undefined : clock(Number(parts[1]), Number(parts[2]));

    if (parts === null || start === undefined) {
        return '08:00';
    }

    const day = 24 * 60;
    const moved =
        (((Number(parts[1]) * 60 + Number(parts[2]) + minutes) % day) + day) %
        day;

    return `${pad(Math.floor(moved / 60))}:${pad(moved % 60)}`;
}
