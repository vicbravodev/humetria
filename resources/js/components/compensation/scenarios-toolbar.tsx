import CountTabs from '@/components/humetria/count-tabs';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import type {
    ScenarioCounts,
    ScenarioFilters,
} from '@/lib/compensation/scenarios';
import { scenarioQuery } from '@/lib/compensation/scenarios';
import { index } from '@/routes/tenant/compensation/scenarios';

/** The active or archived scenarios, each tab with its count. */
export default function ScenariosToolbar({
    filters,
    counts,
}: {
    filters: ScenarioFilters;
    counts: ScenarioCounts;
}) {
    const tenantSlug = useTenantSlug();
    const tab = (status: ScenarioFilters['status']) => ({
        count: counts[status],
        active: filters.status === status,
        href: index.url(tenantSlug, { query: scenarioQuery(status) }),
    });

    return (
        <div className="flex sm:justify-end">
            <CountTabs
                label="Estado de los escenarios"
                tabs={[
                    { label: 'Activos', ...tab('active') },
                    { label: 'Archivados', ...tab('archived') },
                ]}
            />
        </div>
    );
}
