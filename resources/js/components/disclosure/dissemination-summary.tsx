import CountTabs from '@/components/humetria/count-tabs';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { ackQuery } from '@/lib/disclosure';
import { dissemination } from '@/routes/tenant/disclosure';
import type { PolicyAckFilters, PolicyAckStatus } from '@/types';

const VIEWS: { status: PolicyAckStatus; label: string }[] = [
    { status: 'all', label: 'Todas' },
    { status: 'pending', label: 'Pendientes' },
    { status: 'confirmed', label: 'Confirmaron' },
];

/** The views that split the people asked to confirm by state, with their counts (pantalla Eval-Activa, tabs). */
export default function DisseminationSummary({
    version,
    counts,
    filters,
}: {
    version: string | null;
    counts: Record<PolicyAckStatus, number>;
    filters: PolicyAckFilters;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <CountTabs
            label="Estado de las confirmaciones"
            tabs={VIEWS.map((view) => ({
                label: view.label,
                count: counts[view.status],
                active: filters.status === view.status,
                href: dissemination.url(tenantSlug, {
                    query: ackQuery({
                        status: view.status,
                        q: filters.q,
                        version,
                    }),
                }),
            }))}
        />
    );
}
