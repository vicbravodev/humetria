import { fromCalendarDate } from '@/lib/calendar-date';
import { countOf } from '@/lib/plural';
import type { LeaveRequestStatus } from '@/types';

const day = new Intl.DateTimeFormat('es-MX', { day: 'numeric' });
const dayAndMonth = new Intl.DateTimeFormat('es-MX', {
    day: 'numeric',
    month: 'long',
});
const whole = new Intl.DateTimeFormat('es-MX', { dateStyle: 'long' });

/** "9 al 20 de noviembre de 2026": two days (Y-m-d) saying the month and the year once when they share them. */
export function dateSpan(startsOn: string, endsOn: string): string {
    const first = fromCalendarDate(startsOn);
    const last = fromCalendarDate(endsOn);

    if (first === undefined || last === undefined) {
        return '';
    }

    if (startsOn === endsOn) {
        return whole.format(first);
    }

    if (first.getFullYear() !== last.getFullYear()) {
        return `${whole.format(first)} al ${whole.format(last)}`;
    }

    const start =
        first.getMonth() === last.getMonth()
            ? day.format(first)
            : dayAndMonth.format(first);

    return `${start} al ${whole.format(last)}`;
}

/** "9 días laborables". */
export function workingDaysOf(days: number): string {
    return countOf(days, 'día laborable', 'días laborables');
}

const TONES: Record<
    LeaveRequestStatus,
    'default' | 'success' | 'destructive' | 'secondary'
> = {
    requested: 'default',
    approved: 'success',
    rejected: 'destructive',
    cancelled: 'secondary',
    taken: 'secondary',
};

/** The tone of the badge of a request: only the one that waits is in the accent. */
export function statusTone(
    status: LeaveRequestStatus,
): 'default' | 'success' | 'destructive' | 'secondary' {
    return TONES[status];
}
