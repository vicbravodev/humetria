import { duration } from '@/lib/attendance/week-text';
import type { ReportDay, ReportShow } from '@/types';

export type ReportFilters = {
    center: string;
    from: string;
    to: string;
    q: string;
    /** Which people to list; all when it is left out. */
    show?: ReportShow;
};

/** The report's address as the server reads it: the center and the period that are set, a search with text and a filter. */
export function reportQuery(
    filters: ReportFilters,
): Partial<Record<keyof ReportFilters, string>> {
    const q = filters.q.trim();

    return {
        ...(filters.center === '' ? {} : { center: filters.center }),
        ...(filters.from === '' ? {} : { from: filters.from }),
        ...(filters.to === '' ? {} : { to: filters.to }),
        ...(q === '' ? {} : { q }),
        ...(filters.show === undefined || filters.show === 'all'
            ? {}
            : { show: filters.show }),
    };
}

const BADGES: Record<
    ReportDay['confirmation'],
    { label: string; variant: 'success' | 'destructive' | 'warning' }
> = {
    confirmado: { label: 'Confirmada', variant: 'success' },
    objetada: { label: 'Objetada', variant: 'destructive' },
    'sin confirmar': { label: 'Sin confirmar', variant: 'warning' },
};

/** Where the week of a day stands, in a chip: silence is not conformity. */
export function confirmationBadge(confirmation: ReportDay['confirmation']) {
    return BADGES[confirmation];
}

/** The end of a day: its time, «(día siguiente)» for a night, and «sin fin» when it has none. */
export function endText(day: Pick<ReportDay, 'date' | 'end' | 'endDate'>) {
    if (day.end === null) {
        return 'sin fin';
    }

    return day.endDate === day.date ? day.end : `${day.end} (día siguiente)`;
}

/** «8 h · 1 h doble · 30 min triple», «8 h en descanso», or «Sin horas»: only the parts the day has. */
export function dayHours(day: ReportDay): string {
    const parts = [
        day.ordinaryMinutes > 0 ? duration(day.ordinaryMinutes) : '',
        day.doubleMinutes > 0 ? `${duration(day.doubleMinutes)} doble` : '',
        day.tripleMinutes > 0 ? `${duration(day.tripleMinutes)} triple` : '',
        day.restDayMinutes > 0
            ? `${duration(day.restDayMinutes)} en descanso`
            : '',
    ].filter((part) => part !== '');

    return parts.length === 0 ? 'Sin horas' : parts.join(' · ');
}

/** «3 personas · 12 días». */
export function summaryHint(people: number, days: number): string {
    return `${people} ${people === 1 ? 'persona' : 'personas'} · ${days} ${days === 1 ? 'día' : 'días'}`;
}

const DOTS: Record<string, number> = { Fuerte: 3, Media: 2 };

/** How many of the evidence's three dots are filled: Fuerte 3, Media 2, Básica and Corregida 1. */
export function evidenceDots(level: string): number {
    return DOTS[level] ?? 1;
}
