import { Head, setLayoutProps } from '@inertiajs/react';
import CorrectionDetail from '@/components/attendance/correction-detail';
import CorrectionList from '@/components/attendance/correction-list';
import CountTabs from '@/components/humetria/count-tabs';
import PageHeader from '@/components/humetria/page-header';
import ReadOnlyNotice from '@/components/humetria/read-only-notice';
import SearchField from '@/components/humetria/search-field';
import { useCorrectionFilters } from '@/hooks/use-correction-filters';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { correctionsQuery } from '@/lib/attendance/corrections';
import { index } from '@/routes/tenant/attendance/corrections';
import { cn } from '@/lib/utils';
import type {
    CorrectionCounts,
    CorrectionDetail as Detail,
    CorrectionRow,
    CorrectionStatus,
    Paginated,
} from '@/types';

const TABS: { status: CorrectionStatus; label: string }[] = [
    { status: 'pending', label: 'Por resolver' },
    { status: 'approved', label: 'Aprobadas' },
    { status: 'rejected', label: 'Rechazadas' },
];

/**
 * RH's queue of the people's requests to correct their record (criterio 5.4): the list at the left and, at its right, the
 * one in view with its day and what approving it would do, to decide it there.
 */
export default function Corrections({
    requests,
    status,
    filters,
    counts,
    selectedId,
    detail,
    canResolve,
}: {
    requests: Paginated<CorrectionRow>;
    status: CorrectionStatus;
    filters: { q: string };
    counts: CorrectionCounts;
    selectedId: string | null;
    detail: Detail | null;
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
                    description="Cada una agrega una marca nueva. La original se conserva y las dos se ven en el reporte."
                />
                {!canResolve && <ReadOnlyNotice />}
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
                {/* The list beside the request in view; with none to show, the empty queue takes the whole width. */}
                <div
                    className={cn(
                        'grid items-start gap-5',
                        requests.data.length > 0 &&
                            'lg:grid-cols-[380px_minmax(0,1fr)]',
                    )}
                >
                    <CorrectionList
                        counts={counts}
                        requests={requests}
                        selectedId={selectedId}
                        status={status}
                        q={filters.q}
                    />
                    {detail !== null && (
                        <CorrectionDetail
                            detail={detail}
                            canResolve={canResolve}
                            pending={counts.pending}
                            status={status}
                            q={filters.q}
                        />
                    )}
                </div>
            </div>
        </>
    );
}
