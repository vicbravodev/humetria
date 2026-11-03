import type { CaseFilters } from '@/types';

/** The query string for the filters of the inbox: only what narrows it, so a plain list has a plain address. */
export function caseQuery(
    filters: CaseFilters,
): Partial<Record<keyof CaseFilters, string>> {
    const q = filters.q.trim();

    return {
        ...(q === '' ? {} : { q }),
        ...(filters.type === null ? {} : { type: filters.type }),
        ...(filters.center === null ? {} : { center: filters.center.value }),
        ...(filters.status === 'closed' ? { status: 'closed' } : {}),
    };
}
