import { Head, setLayoutProps } from '@inertiajs/react';
import CorrectionsTable from '@/components/attendance/corrections-table';
import CountTabs from '@/components/humetria/count-tabs';
import PageHeader from '@/components/humetria/page-header';
import SearchField from '@/components/humetria/search-field';
import { useCorrectionFilters } from '@/hooks/use-correction-filters';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { correctionsQuery } from '@/lib/attendance/corrections';
import { index } from '@/routes/tenant/attendance/corrections';
import type {
    CorrectionCounts,
    CorrectionRow,
    CorrectionStatus,
    Paginated,
} from '@/types';

const TABS: { status: CorrectionStatus; label: string }[] = [
    { status: 'pending', label: 'Por resolver' },
    { status: 'approved', label: 'Aprobadas' },
    { status: 'rejected', label: 'Rechazadas' },
];

/** RH's queue of the people's requests to correct their record (criterio 5.4): what they ask, why, and since when. */
export default function Corrections({
    requests,
    status,
    filters,
    counts,
    canResolve,
}: {
    requests: Paginated<CorrectionRow>;
    status: CorrectionStatus;
    filters: { q: string };
    counts: CorrectionCounts;
    canResolve: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const { text, type } = useCorrectionFilters(tenantSlug, {
        ...filters,
        status,
    });
    setLayoutProps({
        breadcrumbs: [
            { title: 'Registro de jornada' },
            { title: 'Correcciones' },
        ],
    });

    return (
        <>
            <Head title="Correcciones del registro" />
            <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-6 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    title="Correcciones"
                    description="Lo que las personas piden corregir de su registro. Al aprobar, se agrega una marca corregida y la original se conserva."
                />
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <CountTabs
                        label="Estado de las solicitudes"
                        tabs={TABS.map((tab) => ({
                            label: tab.label,
                            count: counts[tab.status],
                            href: index(tenantSlug, {
                                query: correctionsQuery({
                                    q: filters.q,
                                    status: tab.status,
                                }),
                            }),
                            active: status === tab.status,
                        }))}
                    />
                    <SearchField
                        label="Buscar por nombre"
                        value={text}
                        onChange={type}
                        className="w-full sm:max-w-[320px]"
                    />
                </div>
                <CorrectionsTable
                    requests={requests}
                    status={status}
                    searching={filters.q !== ''}
                    canResolve={canResolve}
                />
            </div>
        </>
    );
}
