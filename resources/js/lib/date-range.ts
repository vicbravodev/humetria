/*
 * A period of days, both ends included, written Y-m-d; an end not chosen yet is empty.
 */
import { toCalendarDate } from '@/lib/calendar-date';

export type DateRange = { from: string; to: string };

/** A period one tap away: its name and its two ends. */
export type RangeShortcut = DateRange & { label: string };

/**
 * A day picked in a period's calendar: the first pick starts a new period (its end left open), the second ends it,
 * turned around when it falls before the start. `done` says the period is whole, so the calendar can close.
 */
export function pickInRange(
    range: DateRange,
    day: string,
    end: 'from' | 'to',
): { range: DateRange; next: 'from' | 'to'; done: boolean } {
    if (end === 'from' || range.from === '') {
        return { range: { from: day, to: '' }, next: 'to', done: false };
    }

    const [from, to] = day < range.from ? [day, range.from] : [range.from, day];

    return { range: { from, to }, next: 'from', done: true };
}

/** The usual periods of a report (as the attendance report offers them): this week up to today, last week, last month. */
export function periodShortcuts(today: Date = new Date()): RangeShortcut[] {
    const day = new Date(
        today.getFullYear(),
        today.getMonth(),
        today.getDate(),
    );
    // Weeks start on Monday: Sunday is the seventh day.
    const monday = new Date(day);
    monday.setDate(day.getDate() - ((day.getDay() + 6) % 7));
    const lastMonday = new Date(monday);
    lastMonday.setDate(monday.getDate() - 7);
    const lastSunday = new Date(monday);
    lastSunday.setDate(monday.getDate() - 1);

    return [
        {
            label: 'Esta semana',
            from: toCalendarDate(monday),
            to: toCalendarDate(day),
        },
        {
            label: 'Semana pasada',
            from: toCalendarDate(lastMonday),
            to: toCalendarDate(lastSunday),
        },
        {
            label: 'Último mes',
            from: toCalendarDate(
                new Date(day.getFullYear(), day.getMonth() - 1, 1),
            ),
            to: toCalendarDate(new Date(day.getFullYear(), day.getMonth(), 0)),
        },
    ];
}
