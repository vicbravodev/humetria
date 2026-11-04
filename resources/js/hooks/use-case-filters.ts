import { useQueryFilters } from '@/hooks/use-query-filters';
import { caseQuery } from '@/lib/complaints/cases';
import { index } from '@/routes/tenant/complaints';
import type { CaseFilters } from '@/types';

/** The inbox's filters: see useQueryFilters. */
export function useCaseFilters(tenantSlug: string, filters: CaseFilters) {
    return useQueryFilters(index.url(tenantSlug), filters, caseQuery);
}
