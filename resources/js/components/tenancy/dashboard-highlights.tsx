import DashboardActiveCard from '@/components/tenancy/dashboard-active-card';
import DashboardNextSteps from '@/components/tenancy/dashboard-next-steps';
import DashboardQuietCard from '@/components/tenancy/dashboard-quiet-card';
import type { ActiveEvaluation, NextStep } from '@/types';

/** The top of the panel, side by side: the evaluation in course that closes soonest and what comes next. */
export default function DashboardHighlights({
    active,
    steps,
    pending,
    canManage,
}: {
    active: ActiveEvaluation | null;
    steps: NextStep[];
    pending: number;
    canManage: boolean;
}) {
    return (
        <section
            aria-label="Lo más importante hoy"
            className="grid grid-cols-[repeat(auto-fit,minmax(min(420px,100%),1fr))] gap-5"
        >
            {active === null ? (
                <DashboardQuietCard />
            ) : (
                <DashboardActiveCard
                    evaluation={active}
                    canManage={canManage}
                />
            )}
            <DashboardNextSteps steps={steps} pending={pending} />
        </section>
    );
}
