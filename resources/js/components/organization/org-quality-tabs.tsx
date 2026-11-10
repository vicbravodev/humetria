import CountTabs from '@/components/humetria/count-tabs';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { quality } from '@/routes/tenant/org-chart';
import type { ReportingIssue } from '@/types';

/** The gaps of the reporting line with how many people each holds, and what the open one means. */
export default function OrgQualityTabs({
    issues,
    current,
}: {
    issues: ReportingIssue[];
    current: ReportingIssue['value'];
}) {
    const tenantSlug = useTenantSlug();

    return (
        <div className="flex flex-col gap-3">
            <CountTabs
                label="Problemas de la línea de reporte"
                tabs={issues.map((issue) => ({
                    label: issue.label,
                    count: issue.count,
                    active: issue.value === current,
                    href: quality.url(tenantSlug, {
                        query: { issue: issue.value },
                    }),
                }))}
            />
            <p className="text-meta text-muted-foreground">
                {issues.find((issue) => issue.value === current)?.hint}
            </p>
        </div>
    );
}
