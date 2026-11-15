import { daysUntil } from '@/lib/leave/overview';
import { countOf } from '@/lib/plural';
import type { LeaveBalanceFilters, LeaveBalanceRow } from '@/types';

/** The query string of the list of balances: the tab, always (the list opens on what asks for action, not on everybody), and the search. */
export function balancesQuery(
    filters: LeaveBalanceFilters,
): Partial<Record<keyof LeaveBalanceFilters, string>> {
    const q = filters.q.trim();

    return {
        tab: filters.tab,
        ...(q === '' ? {} : { q }),
    };
}

/** «6 días disponibles · 2 en espera»: what a person can ask for and what already waits for an answer (the server writes the same line in the people it offers). */
export function balanceHint(available: number, waiting: number): string {
    const text = countOf(available, 'día disponible', 'días disponibles');

    return waiting > 0 ? `${text} · ${waiting} en espera` : text;
}

/** What RH reads under the days of a person: those given in advance, or that they have no start date. */
export function balanceNote(
    row: Pick<LeaveBalanceRow, 'advanced' | 'available' | 'nextAnniversary'>,
): string {
    if (row.nextAnniversary === null) {
        return 'Sin fecha de ingreso';
    }

    if (row.advanced > 0) {
        return countOf(row.advanced, 'día adelantado', 'días adelantados');
    }

    return row.available === 0 ? 'Sin días' : 'Al corriente';
}

/** The days left as a percentage of the days given, for the bar; null when the days given are not known. */
export function availablePercent(
    available: number,
    granted: number | null,
): number | null {
    if (granted === null || granted <= 0) {
        return null;
    }

    return Math.min(100, Math.max(0, Math.round((available / granted) * 100)));
}

/** «Nuevo periodo en 20 días» when the next one starts within a month; nothing otherwise. */
export function newPeriodSoon(
    nextAnniversary: string | null,
    today: string,
): string | null {
    const days =
        nextAnniversary === null ? null : daysUntil(nextAnniversary, today);

    if (days === null || days < 0 || days > 30) {
        return null;
    }

    if (days === 0) {
        return 'Nuevo periodo hoy';
    }

    return days === 1
        ? 'Nuevo periodo mañana'
        : `Nuevo periodo en ${days} días`;
}
