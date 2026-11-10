import { fromCalendarDate } from '@/lib/calendar-date';
import type { LeaveCalendarFilters } from '@/types';

export type CalendarDay = {
    /** Y-m-d. */
    date: string;
    /** The day of the month, from 1. */
    day: number;
    /** 0 for Monday to 6 for Sunday. */
    weekday: number;
};

const WEEKDAY_INITIALS = ['L', 'M', 'M', 'J', 'V', 'S', 'D'];

/** The initial of the day of the week (Monday first), for the head of a column. */
export function weekdayInitial(weekday: number): string {
    return WEEKDAY_INITIALS[weekday] ?? '';
}

/** The days of a month (Y-m), from the first; none when the text is not a month. */
export function daysOfMonth(month: string): CalendarDay[] {
    const first = fromCalendarDate(`${month}-01`);

    if (first === undefined) {
        return [];
    }

    const total = new Date(
        first.getFullYear(),
        first.getMonth() + 1,
        0,
    ).getDate();

    return Array.from({ length: total }, (_, index) => {
        const date = new Date(first.getFullYear(), first.getMonth(), index + 1);

        return {
            date: `${month}-${String(index + 1).padStart(2, '0')}`,
            day: index + 1,
            weekday: (date.getDay() + 6) % 7,
        };
    });
}

/**
 * Where a stretch of the month goes on a row of columns, one per day: the column it starts in (from 1) and how many it
 * takes. A stretch already cut to the month is read as it comes.
 */
export function stretchColumns(
    from: string,
    to: string,
): { column: number; span: number } {
    const start = Number(from.slice(8, 10));
    const end = Number(to.slice(8, 10));

    return { column: start, span: Math.max(1, end - start + 1) };
}

const monthName = new Intl.DateTimeFormat('es-MX', {
    month: 'long',
    year: 'numeric',
});

/** "noviembre de 2026". */
export function monthLabel(month: string): string {
    const first = fromCalendarDate(`${month}-01`);

    return first === undefined ? '' : monthName.format(first);
}

/** The query string of the calendar: the month and only the filters that narrow it. */
export function calendarQuery(
    month: string,
    filters: LeaveCalendarFilters,
): Record<string, string> {
    return {
        month,
        ...(filters.center === null
            ? {}
            : { work_center: filters.center.value }),
        ...(filters.area === null ? {} : { area: filters.area.value }),
    };
}

/** Whether nobody works that day: a weekend or a holiday. */
export function isRestDay(day: CalendarDay, holidays: string[]): boolean {
    return day.weekday >= 5 || holidays.includes(day.date);
}
