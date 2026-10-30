import { Head, usePage } from '@inertiajs/react';
import ConsolePagination from '@/components/console-pagination';
import PageHeader from '@/components/page-header';
import CreateWorkCenterDialog from '@/components/tenancy/create-work-center-dialog';
import WorkCenterViews from '@/components/tenancy/work-center-views';
import WorkCentersEmpty from '@/components/tenancy/work-centers-empty';
import WorkCentersTable from '@/components/tenancy/work-centers-table';
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
                <PageHeader title="Centros de trabajo">
                    {canManage && (
                        <CreateWorkCenterDialog
                            tenantSlug={tenantSlug}
                            states={states}
                        />
                    )}
                </PageHeader>
                {/* Only once there is something archived, or while looking at the archive. */}
                {(counts.archived > 0 || view === 'archived') && (
                    <WorkCenterViews
                        tenantSlug={tenantSlug}
                        view={view}
                        counts={counts}
                    />
                )}
                {workCenters.total === 0 ? (
                    <WorkCentersEmpty view={view} />
                ) : (
                    <WorkCentersTable
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
