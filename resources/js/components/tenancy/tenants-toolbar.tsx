import CountTabs from '@/components/humetria/count-tabs';
import SearchField from '@/components/humetria/search-field';
import TenantsChips from '@/components/tenancy/tenants-chips';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { companiesQuery } from '@/lib/tenant-status';
import { index } from '@/routes/companies';
import type { StaffMember, TenantFilters, TenantView } from '@/types';

const VIEWS: { value: TenantView; label: string }[] = [
    { value: 'all', label: 'Todas' },
    { value: 'active', label: 'Activas' },
    { value: 'trial', label: 'En prueba' },
    { value: 'trial_ending', label: 'Prueba por vencer' },
    { value: 'dormant', label: 'Sin entrar 30 días' },
    { value: 'expired', label: 'Vencidas' },
    { value: 'read_only', label: 'Solo lectura' },
    { value: 'suspended', label: 'Suspendidas' },
    { value: 'archived', label: 'Archivadas' },
];

/** Narrows the console's companies: text in their names or RFC, and one status at a time with how many each holds. */
export default function TenantsToolbar({
    filters,
    counts,
    plans,
    staff,
}: {
    filters: TenantFilters;
    counts: Record<TenantView, number>;
    plans: { value: string; label: string }[];
    staff: StaffMember[];
}) {
    const { text, type } = useQueryFilters(
        index.url(),
        filters,
        companiesQuery,
    );

    return (
        <div role="search" className="flex flex-wrap items-center gap-3">
            <SearchField
                label="Buscar por nombre o RFC"
                value={text}
                onChange={type}
                className="w-full md:w-[380px]"
            />
            <TenantsChips filters={filters} plans={plans} staff={staff} />
            <CountTabs
                label="Estado de las empresas"
                tabs={VIEWS.map((view) => ({
                    label: view.label,
                    count: counts[view.value],
                    href: index.url({
                        query: companiesQuery({
                            ...filters,
                            status: view.value,
                        }),
                    }),
                    active: filters.status === view.value,
                }))}
            />
        </div>
    );
}
