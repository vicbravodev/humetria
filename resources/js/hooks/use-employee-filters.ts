import { useQueryFilters } from '@/hooks/use-query-filters';
import type { EmployeeFilters } from '@/lib/employees';
import { employeeQuery } from '@/lib/employees';
import { index } from '@/routes/tenant/employees';

/** The employees list's filters: see useQueryFilters. */
export function useEmployeeFilters(
    tenantSlug: string,
    filters: EmployeeFilters,
) {
    return useQueryFilters(index.url(tenantSlug), filters, employeeQuery);
}
