import { countOf } from '@/lib/plural';
import type { AccountFilters } from '@/types';

/** The console's line under "Cuentas": how many there are. */
export function accountsSummary(total: number): string {
    return total === 0
        ? 'Aún no hay cuentas'
        : countOf(total, 'cuenta', 'cuentas');
}

/** The list's search and kind as its address carries them: without the defaults. */
export function accountsQuery(
    filters: AccountFilters,
): Partial<Record<keyof AccountFilters, string>> {
    const q = filters.q.trim();

    return {
        ...(q === '' ? {} : { q }),
        ...(filters.kind === 'all' ? {} : { kind: filters.kind }),
    };
}
