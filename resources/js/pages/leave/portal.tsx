import { Head } from '@inertiajs/react';
import { useState } from 'react';
import PortalBalance from '@/components/leave/portal-balance';
import PortalRequests from '@/components/leave/portal-requests';
import PortalStatements from '@/components/leave/portal-statements';
import PortalTeamLink from '@/components/leave/portal-team-link';
import RequestSheet from '@/components/leave/request-sheet';
import { Button } from '@/components/ui/button';
import WorkerFrame from '@/components/worker-access/worker-frame';
import { home } from '@/routes/worker';
import type {
    LeavePeriod,
    LeaveTypeOption,
    PendingStatement,
    Paginated,
    PortalLeaveRequest,
} from '@/types';

/**
 * «Mis vacaciones» in the person's portal, in usted: the days they have in one summary, what waits for their confirmation,
 * what they have asked for, and «Pedir días» stuck to the foot, which opens the form in a sheet (boards VacacionesNueva and
 * PedirDias). Only days and dates, never an amount.
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
    // The sheet is open for the number of requests it was opened at: a sent request changes it, so it closes by itself, whether
    // the request went at once or after the person proved it was them.
    const [openedAt, setOpenedAt] = useState<number | null>(null);

    return (
        <WorkerFrame
            company={company}
            back={home.url(slug)}
            actions={
                <Button
                    className="h-14 w-full text-lg font-semibold"
                    onClick={() => setOpenedAt(requests.total)}
                >
                    Pedir días
                </Button>
            }
        >
            <Head title="Mis vacaciones" />
            <h1 className="text-[26px] leading-[34px] font-semibold tracking-[-0.01em]">
                Mis vacaciones
            </h1>
            <PortalBalance
                periods={periods}
                advanced={advanced}
                waiting={waiting}
            />
            <PortalStatements slug={slug} statements={statements} />
            <PortalTeamLink slug={slug} pending={teamPending} />
            <PortalRequests slug={slug} requests={requests} />
            <RequestSheet
                slug={slug}
                today={today}
                types={types}
                available={periods.reduce(
                    (days, period) => days + period.available,
                    0,
                )}
                open={openedAt === requests.total}
                onOpenChange={(open) =>
                    setOpenedAt(open ? requests.total : null)
                }
            />
        </WorkerFrame>
    );
}
