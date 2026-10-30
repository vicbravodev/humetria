import { Head, Link, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import PageHeader from '@/components/page-header';
import DashboardBody from '@/components/tenancy/dashboard-body';
import { Button } from '@/components/ui/button';
import { greeting, panelSummary } from '@/lib/dashboard';
import { index as campaigns } from '@/routes/tenant/campaigns';
import type {
    ActiveEvaluation,
    ClosedEvaluation,
    CycleRow,
    DashboardCounts,
    NextStep,
    Paginated,
    RiskLevelInfo,
} from '@/types';

type Props = {
    /** "Viernes 30 de octubre", in Mexico City. */
    today: string;
    hasWorkCenters: boolean;
    counts: DashboardCounts;
    active: ActiveEvaluation | null;
    nextSteps: NextStep[];
    pendingSteps: number;
    cycle: Paginated<CycleRow>;
    closed: Paginated<ClosedEvaluation>;
    levels: RiskLevelInfo[];
};

/** The company's panel (pantalla Panel): what is in course, what comes next, the two-year cycle and what closed. */
export default function Dashboard(props: Props) {
    const { auth, tenant } = usePage().props;
    // Always set here: this page only exists inside a company.
    const tenantSlug = tenant?.slug ?? '';
    const canManage = tenant?.can.manage_campaigns ?? false;

    return (
        <>
            <Head title="Panel" />
            <div className="flex w-full max-w-[1180px] flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    caption={props.today}
                    title={greeting(auth.user.name)}
                    description={panelSummary(props.counts)}
                >
                    {canManage && props.hasWorkCenters && (
                        <Button asChild>
                            <Link href={campaigns(tenantSlug)}>
                                <Plus aria-hidden />
                                Nueva evaluación
                            </Link>
                        </Button>
                    )}
                </PageHeader>
                <DashboardBody
                    tenantSlug={tenantSlug}
                    canManage={canManage}
                    data={props}
                />
            </div>
        </>
    );
}
