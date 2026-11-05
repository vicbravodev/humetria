import { fromCalendarDate } from '@/lib/calendar-date';
import type { CaseDeadlines, CaseDetail } from '@/types';

const DAY = 86_400_000;

/** Whole calendar days from one day to another (Y-m-d), negative once it passed; null when either is not a date. */
export function daysUntil(today: string, day: string): number | null {
    const from = fromCalendarDate(today);
    const to = fromCalendarDate(day);

    // Noon to noon: a change of the clock never moves a day.
    return from === undefined || to === undefined
        ? null
        : Math.round(
              (to.getTime() -
                  from.getTime() +
                  (from.getTimezoneOffset() - to.getTimezoneOffset()) *
                      60_000) /
                  DAY,
          );
}

/** «Acuse vence mañana», «Respuesta vence en 18 días», «Acuse venció hace 2 días»: a deadline the company set, from today. */
export function dueText(what: string, today: string, due: string): string {
    const days = daysUntil(today, due);

    if (days === null) {
        return `${what} sin plazo`;
    }

    if (days < 0) {
        return `${what} venció hace ${-days} ${-days === 1 ? 'día' : 'días'}`;
    }

    const when = days === 0 ? 'hoy' : days === 1 ? 'mañana' : `en ${days} días`;

    return `${what} vence ${when}`;
}

/** A deadline that falls today or tomorrow, or already passed: what a handler attends first. */
export function isUrgent(today: string, due: string): boolean {
    const days = daysUntil(today, due);

    return days !== null && days <= 1;
}

/** How much of the time between the day a case came and its deadline went by: from 0 to 1. */
export function elapsed(received: string, due: string, today: string): number {
    const total = daysUntil(received, due);
    const gone = daysUntil(received, today);

    if (total === null || gone === null) {
        return 0;
    }

    return total <= 0 ? 1 : Math.min(1, Math.max(0, gone / total));
}

const short = new Intl.DateTimeFormat('es-MX', {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
});

/** «lun 2 nov»: a day as the inbox and the steps of a case read it; the same text when it is not a date. */
export function shortDay(text: string): string {
    const date = fromCalendarDate(text);

    if (date === undefined) {
        return text;
    }

    const part = (type: Intl.DateTimeFormatPartTypes) =>
        short
            .formatToParts(date)
            .find((each) => each.type === type)
            ?.value.replaceAll('.', '') ?? '';

    return `${part('weekday')} ${part('day')} ${part('month')}`;
}

export type CaseStep = { label: string; day: string | null; done: boolean };

/** The four steps of a case (board V4-Quejas-Caso): received, acknowledged, in review and closed, with their day when kept. */
export function caseSteps(
    status: CaseDetail['status'],
    receivedOn: string,
    deadlines: CaseDeadlines,
): CaseStep[] {
    const day = (text: string | null) =>
        text === null ? null : shortDay(text);

    return [
        { label: 'Recibida', day: shortDay(receivedOn), done: true },
        {
            label: 'Acuse enviado',
            day: day(deadlines.acknowledgedOn),
            done: deadlines.acknowledgedOn !== null,
        },
        { label: 'En revisión', day: null, done: status !== 'new' },
        {
            label: 'Cerrada',
            day: day(deadlines.closedOn),
            done: status === 'closed',
        },
    ];
}
