import { Head, usePage } from '@inertiajs/react';
import PageHeader from '@/components/humetria/page-header';
import Pagination from '@/components/humetria/pagination';
import PlanLimitNotice from '@/components/humetria/plan-limit-notice';
import CreateWorkCenterDialog from '@/components/tenancy/create-work-center-dialog';
import WorkCentersList from '@/components/tenancy/work-centers-list';
import WorkCentersToolbar from '@/components/tenancy/work-centers-toolbar';
import { atLimit } from '@/lib/limits';
import type {
    MexicanStateOption,
    Paginated,
    PlanLimit,
    WorkCenterRow,
    WorkCentersView,
} from '@/types';

export default function WorkCenters({
    view,
    search,
    plan_limit: planLimit,
    counts,
    workCenters,
    states,
}: {
    view: WorkCentersView;
    search: string;
    plan_limit: PlanLimit | null;
    counts: Record<WorkCentersView, number>;
    workCenters: Paginated<WorkCenterRow>;
    states: MexicanStateOption[];
}) {
    // Always set here: this page only exists inside a company.
    const { tenant } = usePage().props;
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
                            states={states}
                            full={atLimit(planLimit)}
                        />
                    )}
                </PageHeader>
                <PlanLimitNotice
                    limit={planLimit}
                    what="centros"
                    noun={{
                        singular: 'centro de trabajo',
                        plural: 'centros de trabajo',
                    }}
                />
                <WorkCentersToolbar
                    view={view}
                    search={search}
                    counts={counts}
                />
                <WorkCentersList
                    view={view}
                    search={search}
                    workCenters={workCenters}
                    manage={
                        canManage ? { states, full: atLimit(planLimit) } : null
                    }
                />
                <Pagination
                    page={workCenters}
                    label="Páginas de centros de trabajo"
                />
            </div>
        </>
    );
}
