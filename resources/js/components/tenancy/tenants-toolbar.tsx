import CountTabs from '@/components/humetria/count-tabs';
import SearchField from '@/components/humetria/search-field';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { companiesQuery } from '@/lib/tenant-status';
import { index } from '@/routes/companies';
import type { TenantFilters, TenantView } from '@/types';

const VIEWS: { value: TenantView; label: string }[] = [
    { value: 'all', label: 'Todas' },
    { value: 'active', label: 'Activas' },
    { value: 'trial', label: 'En prueba' },
    { value: 'expired', label: 'Vencidas' },
    { value: 'deactivated', label: 'Desactivadas' },
];

/** Narrows the console's companies: text in their names or RFC, and one status at a time with how many each holds. */
export default function TenantsToolbar({
    filters,
    counts,
}: {
    filters: TenantFilters;
    counts: Record<TenantView, number>;
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
