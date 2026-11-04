import { useQueryFilters } from '@/hooks/use-query-filters';
import type { BenefitFilters } from '@/lib/compensation/benefits';
import { benefitQuery } from '@/lib/compensation/benefits';
import { index } from '@/routes/tenant/compensation/benefits';

/** The benefits list's filters: see useQueryFilters. */
export function useBenefitFilters(tenantSlug: string, filters: BenefitFilters) {
    return useQueryFilters(index.url(tenantSlug), filters, benefitQuery);
}
