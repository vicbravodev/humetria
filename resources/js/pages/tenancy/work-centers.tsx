import { Head, usePage } from '@inertiajs/react';
import ConsolePagination from '@/components/console-pagination';
import CountTabs from '@/components/humetria/count-tabs';
import PageHeader from '@/components/page-header';
import CreateWorkCenterDialog from '@/components/tenancy/create-work-center-dialog';
import WorkCentersEmpty from '@/components/tenancy/work-centers-empty';
import WorkCentersGrid from '@/components/tenancy/work-centers-grid';
import { index } from '@/routes/tenant/work-centers';
import type {
    MexicanStateOption,
    Paginated,
    WorkCenterRow,
    WorkCentersView,
} from '@/types';

export default function WorkCenters({
    view,
    counts,
    workCenters,
    states,
}: {
    view: WorkCentersView;
    counts: Record<WorkCentersView, number>;
    workCenters: Paginated<WorkCenterRow>;
    states: MexicanStateOption[];
}) {
    // Always set here: this page only exists inside a company.
    const { tenant } = usePage().props;
    const tenantSlug = tenant?.slug ?? '';
    const canManage = tenant?.can.manage_organization ?? false;

    return (
        <>
            <Head title="Centros de trabajo" />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    title="Centros de trabajo"
                    description="Cada centro se evalúa por separado. El número de personas decide qué guías aplican."
                >
                    {canManage && (
                        <CreateWorkCenterDialog
                            tenantSlug={tenantSlug}
                            states={states}
                        />
                    )}
                </PageHeader>
                {/* Only once there is something archived, or while looking at the archive. */}
                {(counts.archived > 0 || view === 'archived') && (
                    <div>
                        <CountTabs
                            label="Vistas de centros de trabajo"
                            tabs={[
                                {
                                    label: 'Activos',
                                    count: counts.active,
                                    href: index.url(tenantSlug),
                                    active: view === 'active',
                                },
                                {
                                    label: 'Archivados',
                                    count: counts.archived,
                                    href: index.url(tenantSlug, {
                                        query: { archived: 1 },
                                    }),
                                    active: view === 'archived',
                                },
                            ]}
                        />
                    </div>
                )}
                {workCenters.total === 0 ? (
                    <WorkCentersEmpty view={view} />
                ) : (
                    <WorkCentersGrid
                        tenantSlug={tenantSlug}
                        view={view}
                        workCenters={workCenters.data}
                        states={canManage ? states : null}
                    />
                )}
                <ConsolePagination
                    page={workCenters}
                    label="Páginas de centros de trabajo"
                    previous="Anteriores"
                    next="Siguientes"
                />
            </div>
        </>
    );
}
