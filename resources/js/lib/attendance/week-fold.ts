import type { WeekDay } from '@/lib/attendance/week';
import { absenceLabel, dayTitle } from '@/lib/attendance/week-text';

/** A date of the server (Y-m-d) as its parts; read as a calendar date, never in the browser's time zone. */
function parts(date: string): { year: number; month: number; day: number } {
    const [year = 0, month = 1, day = 1] = date.split('-').map(Number);

    return { year, month, day };
}

const SHORT_MONTHS = [
    'ene',
    'feb',
    'mar',
    'abr',
    'may',
    'jun',
    'jul',
    'ago',
    'sep',
    'oct',
    'nov',
    'dic',
];

/** «9 nov 2026», with the year only when asked. */
function shortDate(date: string, year: boolean): string {
    const at = parts(date);
    const day = `${at.day} ${SHORT_MONTHS[at.month - 1] ?? ''}`;

    return year ? `${day} ${at.year}` : day;
}

/** The week in the bar between its arrows: «9 a 15 nov 2026», «30 nov a 6 dic 2026», «28 dic 2026 a 3 ene 2027». */
export function weekRange(start: string, end: string): string {
    const from = parts(start);
    const to = parts(end);

    if (from.year !== to.year) {
        return `${shortDate(start, true)} a ${shortDate(end, true)}`;
    }

    return from.month === to.month
        ? `${from.day} a ${shortDate(end, true)}`
        : `${shortDate(start, false)} a ${shortDate(end, true)}`;
}

/** A day of the week, folded: «Lun 9». */
export function dayShort(date: string): string {
    return `${dayTitle(date).slice(0, 3)} ${parts(date).day}`;
}

/** What a folded day says next to its name: the range of its marks, or why it has none. */
export function dayRange(day: WeekDay): string {
    const first = day.marks.at(0);
    const last = day.marks.at(-1);

    if (first === undefined || last === undefined) {
        return (
            absenceLabel(day.absence) ??
            (day.restDay ? 'Descanso' : 'Sin marcas')
        );
    }

    const end = last.nextDay ? `${last.time} del día siguiente` : last.time;

    return first === last ? first.time : `${first.time} a ${end}`;
}

/** A day opens by itself only when something in it asks to be looked at: a mark corrected, a lateness, no end marked. */
export function dayNeedsLook(day: WeekDay): boolean {
    return (
        day.missingEnd ||
        (day.lateMinutes ?? 0) > 0 ||
        day.marks.some((mark) => mark.corrected)
    );
}
