import { useQueryFilters } from '@/hooks/use-query-filters';
import { qualityQuery } from '@/lib/org-chart/quality';
import type { QualityFilters } from '@/lib/org-chart/quality';
import { quality } from '@/routes/tenant/org-chart';
import type { ReportingIssue } from '@/types';

/** The filters of the list of the reporting line: see useQueryFilters. They stay in the view that is open. */
export function useQualityFilters(
    tenantSlug: string,
    filters: QualityFilters,
    issue: ReportingIssue['value'],
) {
    return useQueryFilters(
        quality.url(tenantSlug),
        filters,
        (next: QualityFilters) => qualityQuery(next, issue),
    );
}
