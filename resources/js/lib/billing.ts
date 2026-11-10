import { countOf } from '@/lib/plural';
import type { AccountFilters, AccountSummary } from '@/types';

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

/** The notes under the figures of an account: what is missing a plan, what needs a quotation, how much of the total is VAT; undefined where there is nothing to say. */
export function figureHints(summary: AccountSummary) {
    return {
        companies:
            summary.without_plan === 0
                ? 'Todas con plan'
                : `${summary.without_plan} sin plan asignado`,
        subtotal:
            summary.requiring_quotation === 0
                ? undefined
                : `${countOf(summary.requiring_quotation, 'empresa requiere', 'empresas requieren')} cotización`,
        total: summary.vat === null ? undefined : `${summary.vat} de IVA`,
    };
}
