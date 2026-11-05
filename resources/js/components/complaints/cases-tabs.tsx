import CountTabs from '@/components/humetria/count-tabs';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { caseQuery } from '@/lib/complaints/cases';
import { index } from '@/routes/tenant/complaints';
import type { CaseCounts, CaseFilters } from '@/types';

/** The open and the closed cases of the inbox, with how many each holds; the filters carry over. */
export default function CasesTabs({
    filters,
    counts,
}: {
    filters: CaseFilters;
    counts: CaseCounts;
}) {
    const tenantSlug = useTenantSlug();
    const tab = (status: CaseFilters['status']) => ({
        count: counts[status],
        active: filters.status === status,
        href: index.url(tenantSlug, {
            query: caseQuery({ ...filters, status }),
        }),
    });

    return (
        <CountTabs
            label="Estado de los casos"
            tabs={[
                { label: 'Abiertos', ...tab('open') },
                { label: 'Cerrados', ...tab('closed') },
            ]}
        />
    );
}
