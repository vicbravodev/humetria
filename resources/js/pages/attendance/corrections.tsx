import { Head, setLayoutProps } from '@inertiajs/react';
import CorrectionRequests from '@/components/attendance/correction-requests';
import OfflineQueue from '@/components/attendance/offline-queue';
import CountTabs from '@/components/humetria/count-tabs';
import PageHeader from '@/components/humetria/page-header';
import ReadOnlyNotice from '@/components/humetria/read-only-notice';
import SearchField from '@/components/humetria/search-field';
import { useCorrectionFilters } from '@/hooks/use-correction-filters';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { correctionsQuery } from '@/lib/attendance/corrections';
import { index } from '@/routes/tenant/attendance/corrections';
import type { CorrectionView, CorrectionsProps } from '@/types';

const TABS: { status: CorrectionView; label: string }[] = [
    { status: 'pending', label: 'Por resolver' },
    { status: 'approved', label: 'Aprobadas' },
    { status: 'rejected', label: 'Rechazadas' },
    { status: 'offline', label: 'Marcas sin conexión' },
    { status: 'not_accepted', label: 'No aceptadas' },
];

/**
 * RH's queue of the people's requests to correct their record (criterio 5.4) and of the marks made without a connection:
 * the list at the left and, at its right, the one in view with its day and what deciding it would do, to decide it there.
 */
export default function Corrections(props: CorrectionsProps) {
    const tenantSlug = useTenantSlug();
    const { status, filters, counts, canResolve } = props;
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
            <div className="flex w-full flex-col gap-6 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
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
                {props.status === 'offline' ||
                props.status === 'not_accepted' ? (
                    <OfflineQueue queue={props} />
                ) : (
                    <CorrectionRequests queue={props} />
                )}
            </div>
        </>
    );
}
