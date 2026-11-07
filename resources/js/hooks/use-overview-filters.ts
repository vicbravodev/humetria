import { useQueryFilters } from '@/hooks/use-query-filters';
import { overviewQuery } from '@/lib/attendance/overview';
import type { OverviewFilters } from '@/lib/attendance/overview';
import { index } from '@/routes/tenant/attendance';

/** The search of the overview's people, within its week, center and tab: see useQueryFilters. */
export function useOverviewFilters(
    tenantSlug: string,
    filters: OverviewFilters,
) {
    return useQueryFilters(index.url(tenantSlug), filters, overviewQuery);
}
