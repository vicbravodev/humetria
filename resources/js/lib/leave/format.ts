import { fromCalendarDate } from '@/lib/calendar-date';
import { dateRange } from '@/lib/evaluation';
import { countOf, plural } from '@/lib/plural';
import type { LeaveRequestStatus, TeamLeaveRequest } from '@/types';

const day = new Intl.DateTimeFormat('es-MX', { day: 'numeric' });
const dayAndMonth = new Intl.DateTimeFormat('es-MX', {
    day: 'numeric',
    month: 'long',
});
const whole = new Intl.DateTimeFormat('es-MX', { dateStyle: 'long' });
const compact = new Intl.DateTimeFormat('es-MX', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
});

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

const list = new Intl.ListFormat('es', { type: 'conjunction' });

/** What whoever approves reads about the days of a request: where they would come from, or how many are missing. */
export function balanceLine(
    request: Pick<
        TeamLeaveRequest,
        'type' | 'periods' | 'shortBy' | 'allowsAdvance'
    >,
): string {
    if (request.type !== null) {
        return 'Este permiso no usa días de vacaciones.';
    }

    const periods = list.format(
        request.periods.map((period) => `${period.year} (${period.days})`),
    );

    if (request.shortBy === 0) {
        return `Tiene días disponibles: del periodo ${periods}.`;
    }

    const missing = `${plural(request.shortBy, 'le falta', 'le faltan')} ${request.shortBy}`;
    const advance = request.allowsAdvance
        ? 'Su política permite adelantarlos: si aprueba, se le adelantan.'
        : 'Su política no permite adelantar días, así que no se puede aprobar.';

    return request.periods.length === 0
        ? `No tiene días disponibles (${missing}). ${advance}`
        : `Solo tiene días del periodo ${periods}: ${missing}. ${advance}`;
}

/** "1 mar 2027": a day (Y-m-d) short, with its year. */
export function shortDate(day: string): string {
    const date = fromCalendarDate(day);

    return date === undefined
        ? ''
        : compact.format(date).replaceAll('.', '').replace(/ de /g, ' ');
}

/** "23 al 27 nov 2026": two days (Y-m-d) short, the month and the year said once when they are shared; for a narrow cell. */
export function shortSpan(startsOn: string, endsOn: string): string {
    const first = shortDate(startsOn);
    const last = shortDate(endsOn);

    if (first === '' || last === '') {
        return '';
    }

    return startsOn === endsOn ? first : dateRange(first, last);
}
