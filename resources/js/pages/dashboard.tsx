import { Head, usePage } from '@inertiajs/react';
import PageHeader from '@/components/humetria/page-header';
import DashboardNextStep from '@/components/tenancy/dashboard-next-step';
import DashboardNoCenters from '@/components/tenancy/dashboard-no-centers';
import DashboardRoutes from '@/components/tenancy/dashboard-routes';
import DashboardUrgencies from '@/components/tenancy/dashboard-urgencies';
import DashboardWeek from '@/components/tenancy/dashboard-week';
import { greeting, panelSummary } from '@/lib/dashboard';
import type {
    CenterRoutes,
    DashboardSummary,
    Paginated,
    Urgency,
    WeekItem,
} from '@/types';

type Props = {
    /** "Viernes 30 de octubre", in Mexico City. */
    today: string;
    hasWorkCenters: boolean;
    summary: DashboardSummary;
    urgencies: Urgency[];
    week: WeekItem[];
    centers: Paginated<CenterRoutes>;
};

/**
 * The company's panel (rediseño v2, board V2-Panel): what to attend first and where each route goes, with the next step
 * and the week at the side; the side drops below the content under 1240 px.
 */
export default function Dashboard(props: Props) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Panel" />
            <div className="flex w-full flex-col gap-9 px-4 py-6 md:px-12 md:py-10">
                <PageHeader
                    caption={props.today}
                    title={greeting(auth.user.name)}
                    description={panelSummary(props.summary)}
                />
                {props.hasWorkCenters ? (
                    <div className="grid items-start gap-8 min-[1240px]:grid-cols-[minmax(0,1fr)_340px]">
                        <div className="flex min-w-0 flex-col gap-9">
                            <DashboardUrgencies items={props.urgencies} />
                            <DashboardRoutes centers={props.centers} />
                        </div>
                        <aside className="flex flex-col gap-4">
                            <DashboardNextStep centers={props.centers.data} />
                            <DashboardWeek items={props.week} />
                        </aside>
                    </div>
                ) : (
                    <DashboardNoCenters />
                )}
            </div>
        </>
    );
}
