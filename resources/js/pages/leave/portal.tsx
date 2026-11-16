import { Head } from '@inertiajs/react';
import PortalBalance from '@/components/leave/portal-balance';
import PortalRequests from '@/components/leave/portal-requests';
import PortalShell from '@/components/leave/portal-shell';
import PortalStatements from '@/components/leave/portal-statements';
import PortalTeamLink from '@/components/leave/portal-team-link';
import RequestForm from '@/components/leave/request-form';
import WorkerHomeLink from '@/components/worker-home-link';
import type {
    LeavePeriod,
    LeaveTypeOption,
    PendingStatement,
    Paginated,
    PortalLeaveRequest,
} from '@/types';

/**
 * «Mis vacaciones» in the person's portal, in usted: the days they have by period, the form to ask for days, and what they have asked for. Only
 * days and dates, never an amount.
 */
export default function LeavePortal({
    slug,
    company,
    today,
    periods,
    advanced,
    waiting,
    teamPending,
    statements,
    requests,
    types,
}: {
    slug: string;
    company: string;
    name: string;
    /** Today as the company lives it, Y-m-d. */
    today: string;
    periods: LeavePeriod[];
    advanced: number;
    /** Working days asked for and not answered yet. */
    waiting: number;
    /** How many requests of their team wait for their answer. */
    teamPending: number;
    /** The annual statements they have yet to confirm. */
    statements: PendingStatement[];
    requests: Paginated<PortalLeaveRequest>;
    types: LeaveTypeOption[];
}) {
    return (
        <PortalShell company={company}>
            <Head title="Mis vacaciones" />
            <div className="flex w-full max-w-2xl flex-col gap-6">
                <WorkerHomeLink slug={slug} />
                <div className="flex flex-col gap-2">
                    <h1 className="text-[26px] leading-[34px] font-semibold tracking-[-0.01em]">
                        Mis vacaciones
                    </h1>
                    <p className="text-muted-foreground">
                        Sus días de vacaciones y lo que ha pedido, como los
                        tiene registrados su empresa.
                    </p>
                </div>
                <PortalStatements slug={slug} statements={statements} />
                <PortalTeamLink slug={slug} pending={teamPending} />
                <PortalBalance
                    periods={periods}
                    advanced={advanced}
                    waiting={waiting}
                />
                <RequestForm slug={slug} today={today} types={types} />
                <PortalRequests slug={slug} requests={requests} />
            </div>
        </PortalShell>
    );
}
