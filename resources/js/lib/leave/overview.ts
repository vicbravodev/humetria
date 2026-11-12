import { fromCalendarDate } from '@/lib/calendar-date';
import { countOf, plural } from '@/lib/plural';
import type { LeaveAheadDay, LeaveCoverage } from '@/types';

const dayAndMonth = new Intl.DateTimeFormat('es-MX', {
    day: 'numeric',
    month: 'long',
});
const withWeekday = new Intl.DateTimeFormat('es-MX', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
});
const weekday = new Intl.DateTimeFormat('es-MX', { weekday: 'short' });

const MS_PER_DAY = 86_400_000;

/** The whole days from `today` to `day` (both Y-m-d), counted on the calendar so the change of the clocks never moves one. */
export function daysUntil(day: string, today: string): number | null {
    const to = fromCalendarDate(day);
    const from = fromCalendarDate(today);

    if (to === undefined || from === undefined) {
        return null;
    }

    const utc = (date: Date) =>
        Date.UTC(date.getFullYear(), date.getMonth(), date.getDate());

    return Math.round((utc(to) - utc(from)) / MS_PER_DAY);
}

/** The sentence that answers «¿qué me toca hoy?»: how many wait for RH and when the closest one starts. */
export function readingLine(
    waiting: number,
    firstStartsOn: string | null,
    today: string,
): string {
    if (waiting === 0) {
        return 'Estás al día. Nadie espera respuesta.';
    }

    const first =
        firstStartsOn === null ? undefined : fromCalendarDate(firstStartsOn);
    const subject =
        waiting === 1 ? '1 solicitud espera' : `${waiting} solicitudes esperan`;
    const closest =
        first === undefined || daysUntil(firstStartsOn ?? '', today) === null
            ? ''
            : ` La más próxima empieza el ${dayAndMonth.format(first)}.`;

    return `${subject} tu respuesta.${closest}`;
}

/** «Empieza en 7 días»: how close a request is; nothing once it already started. */
export function startsIn(startsOn: string, today: string): string | null {
    const days = daysUntil(startsOn, today);

    if (days === null || days < 0) {
        return null;
    }

    if (days === 0) {
        return 'Empieza hoy';
    }

    return days === 1 ? 'Empieza mañana' : `Empieza en ${days} días`;
}

/** Why a request reached RH, with the name of the manager who did not answer when there is one. */
export function reasonLine(
    reason: string | null,
    manager: string | null,
): string {
    if (reason === null) {
        return '';
    }

    return manager !== null && reason.startsWith('Su jefe ')
        ? `Su jefe (${manager}) ${reason.slice('Su jefe '.length)}`
        : reason;
}

/** «Sí le alcanza · le quedarían 20 días» or «No le alcanza · le faltan 3 días»; nothing for a leave. */
export function coverageLine(coverage: LeaveCoverage | null): string {
    if (coverage === null) {
        return '';
    }

    if (!coverage.fits) {
        const missing = -coverage.leftAfter;

        return `No le alcanza · ${plural(missing, 'le falta', 'le faltan')} ${countOf(missing, 'día', 'días')}`;
    }

    if (coverage.leftAfter === 0) {
        return 'Sí le alcanza · no le quedarían días';
    }

    return `Sí le alcanza · ${plural(coverage.leftAfter, 'le quedaría', 'le quedarían')} ${countOf(coverage.leftAfter, 'día', 'días')}`;
}

/** Who is out, in a line: today, or the most in a day of the next seven when nobody is. */
export function outLine(ahead: LeaveAheadDay[]): string {
    const today = ahead[0]?.away ?? 0;

    if (today > 0) {
        return `${countOf(today, 'persona', 'personas')} fuera hoy`;
    }

    const week = Math.max(0, ...ahead.slice(1, 7).map((day) => day.away));

    return week === 0
        ? 'Nadie está fuera hoy ni esta semana'
        : `Nadie está fuera hoy · hasta ${week} en los próximos 7 días`;
}

/** The weekday and the number of a day of the strip: «mié» and «11». */
export function stripDay(day: string): { weekday: string; number: string } {
    const date = fromCalendarDate(day);

    return date === undefined
        ? { weekday: '', number: '' }
        : {
              weekday: weekday.format(date).replace('.', ''),
              number: String(date.getDate()),
          };
}

/** «Miércoles 11 de noviembre». */
export function dateFact(day: string): string {
    const date = fromCalendarDate(day);

    if (date === undefined) {
        return '';
    }

    const text = withWeekday.format(date).replace(',', '');

    return text.charAt(0).toUpperCase() + text.slice(1);
}
