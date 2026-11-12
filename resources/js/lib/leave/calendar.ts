import { fromCalendarDate } from '@/lib/calendar-date';
import type { LeaveCalendarFilters, LeaveCalendarPerson } from '@/types';

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
    everybody = false,
): Record<string, string> {
    return {
        month,
        ...(everybody ? { with_absence: '0' } : {}),
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

/** The days of the week (Monday to Sunday) that holds `focus`, cut to the month; the first week when `focus` is not in it. */
export function weekOf(days: CalendarDay[], focus: string): CalendarDay[] {
    const found = days.find((day) => day.date === focus) ?? days[0];

    if (found === undefined) {
        return [];
    }

    const monday = found.day - found.weekday;

    return days.filter((day) => day.day >= monday && day.day <= monday + 6);
}

/** «17» for one day, «17–18» for a span, of two days of the same month (Y-m-d). */
export function stretchText(from: string, to: string): string {
    const first = Number(from.slice(8, 10));
    const last = Number(to.slice(8, 10));

    return first === last ? String(first) : `${first}–${last}`;
}

/** Who is away in a week, with the days of each cut to the week (the people come in the order the server gave them). */
export function weekPeople(
    people: LeaveCalendarPerson[],
    week: CalendarDay[],
): { id: string; name: string; text: string; pending: boolean }[] {
    const first = week[0]?.date ?? '';
    const last = week[week.length - 1]?.date ?? '';

    return people.flatMap((person) => {
        const inside = person.absences.filter(
            (stretch) => stretch.from <= last && stretch.to >= first,
        );

        return inside.length === 0
            ? []
            : [
                  {
                      id: person.id,
                      name: person.name,
                      text: inside
                          .map((stretch) =>
                              stretchText(
                                  stretch.from < first ? first : stretch.from,
                                  stretch.to > last ? last : stretch.to,
                              ),
                          )
                          .join(', '),
                      pending: inside.some((stretch) => stretch.pending),
                  },
              ];
    });
}

/** The weeks of the month, Monday to Sunday, the first and the last cut to the month. */
export function weeksOf(days: CalendarDay[]): CalendarDay[][] {
    return days.reduce<CalendarDay[][]>((weeks, day) => {
        const last = weeks[weeks.length - 1];

        if (last === undefined || day.weekday === 0) {
            weeks.push([day]);
        } else {
            last.push(day);
        }

        return weeks;
    }, []);
}

const monthOnly = new Intl.DateTimeFormat('es-MX', { month: 'long' });

/** "diciembre": the month alone, for a sentence that already says the year is this one. */
export function monthWord(month: string): string {
    const first = fromCalendarDate(`${month}-01`);

    return first === undefined ? '' : monthOnly.format(first);
}
