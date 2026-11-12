import { countOf } from '@/lib/plural';
import type { LeaveBalanceFilters, LeaveBalanceRow } from '@/types';

/** The query string of the list of balances: only what narrows it, so the first tab with no search has a plain address. */
export function balancesQuery(
    filters: LeaveBalanceFilters,
): Partial<Record<keyof LeaveBalanceFilters, string>> {
    const q = filters.q.trim();

    return {
        ...(filters.tab === 'all' ? {} : { tab: filters.tab }),
        ...(q === '' ? {} : { q }),
    };
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
