import { Link } from '@inertiajs/react';
import { ClipboardList, Factory } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import DashboardClosedTable from '@/components/tenancy/dashboard-closed-table';
import DashboardCycle from '@/components/tenancy/dashboard-cycle';
import DashboardHighlights from '@/components/tenancy/dashboard-highlights';
import { Button } from '@/components/ui/button';
import { dashboardView } from '@/lib/dashboard';
import { index as workCenters } from '@/routes/tenant/work-centers';
import type {
    ActiveEvaluation,
    ClosedEvaluation,
    CycleRow,
    NextStep,
    Paginated,
    RiskLevelInfo,
} from '@/types';

export type DashboardData = {
    hasWorkCenters: boolean;
    active: ActiveEvaluation | null;
    nextSteps: NextStep[];
    pendingSteps: number;
    cycle: Paginated<CycleRow>;
    closed: Paginated<ClosedEvaluation>;
    levels: RiskLevelInfo[];
};

/** Everything under the panel's header, or the empty state that says what to do first. */
export default function DashboardBody({
    tenantSlug,
    canManage,
    data,
}: {
    tenantSlug: string;
    canManage: boolean;
    data: DashboardData;
}) {
    const view = dashboardView({
        hasWorkCenters: data.hasWorkCenters,
        hasActive: data.active !== null,
        steps: data.nextSteps.length,
        closed: data.closed.total,
    });

    if (view === 'no-work-centers') {
        return (
            <EmptyState
                icon={Factory}
                title="Empieza por tus centros de trabajo"
            >
                Cada evaluación se hace por centro de trabajo. Da de alta el
                primero para empezar.
                <Button asChild className="mx-auto mt-6 block w-fit">
                    <Link href={workCenters(tenantSlug)}>
                        Ir a centros de trabajo
                    </Link>
                </Button>
            </EmptyState>
        );
    }

    return (
        <>
            {view === 'no-campaigns' ? (
                <EmptyState icon={ClipboardList} title="Aún no hay campañas">
                    Cuando lances la primera evaluación, aquí verás su avance.
                </EmptyState>
            ) : (
                <DashboardHighlights
                    tenantSlug={tenantSlug}
                    active={data.active}
                    steps={data.nextSteps}
                    pending={data.pendingSteps}
                    canManage={canManage}
                />
            )}
            <DashboardCycle tenantSlug={tenantSlug} cycle={data.cycle} />
            {data.closed.total > 0 && (
                <DashboardClosedTable
                    tenantSlug={tenantSlug}
                    closed={data.closed}
                    levels={data.levels}
                />
            )}
        </>
    );
}
