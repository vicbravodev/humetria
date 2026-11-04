import { useQueryFilters } from '@/hooks/use-query-filters';
import type { SalaryFilters } from '@/lib/compensation/salaries';
import { salaryQuery } from '@/lib/compensation/salaries';
import { index } from '@/routes/tenant/compensation/overrides';

/** The individual salaries' search: see useQueryFilters. */
export function useOverrideFilters(tenantSlug: string, filters: SalaryFilters) {
    return useQueryFilters(index.url(tenantSlug), filters, salaryQuery);
}
