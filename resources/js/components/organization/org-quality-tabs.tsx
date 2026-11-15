import CountTabs from '@/components/humetria/count-tabs';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { qualityTabs } from '@/lib/org-chart/quality';
import type { QualityFilters } from '@/lib/org-chart/quality';
import { quality } from '@/routes/tenant/org-chart';
import type { ReportingIssue } from '@/types';

/**
 * The views of the list with how many people each holds, what asks for action first; a view with nobody waits under
 * «Otros filtros». The search and the work center go along when changing view.
 */
export default function OrgQualityTabs({
    issues,
    current,
    filters,
}: {
    issues: ReportingIssue[];
    current: ReportingIssue['value'];
    filters: QualityFilters;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <CountTabs
            label="Vistas de la lista"
            byAction
            tabs={qualityTabs(issues, current, filters).map(
                ({ query, ...tab }) => ({
                    ...tab,
                    href: quality.url(tenantSlug, { query }),
                }),
            )}
        />
    );
}
