import { useQueryFilters } from '@/hooks/use-query-filters';
import { correctionsQuery } from '@/lib/attendance/corrections';
import { index } from '@/routes/tenant/attendance/corrections';

/** The search of the queue of corrections, within its tab (and, in the marks made without a connection, its filter): see useQueryFilters. */
export function useCorrectionFilters(
    tenantSlug: string,
    filters: { q: string; status: string; flags?: string },
) {
    return useQueryFilters(index.url(tenantSlug), filters, correctionsQuery);
}
