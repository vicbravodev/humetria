import { useQueryFilters } from '@/hooks/use-query-filters';
import type { SalaryFilters } from '@/lib/compensation/salaries';
import { salaryQuery } from '@/lib/compensation/salaries';
import { index } from '@/routes/tenant/compensation/tabulator';

/** The tabulator's search: see useQueryFilters. */
export function useTabulatorFilters(
    tenantSlug: string,
    filters: SalaryFilters,
) {
    return useQueryFilters(index.url(tenantSlug), filters, salaryQuery);
}
