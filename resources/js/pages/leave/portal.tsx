import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import PortalBalance from '@/components/leave/portal-balance';
import PortalRequests from '@/components/leave/portal-requests';
import PortalShell from '@/components/leave/portal-shell';
import { show } from '@/routes/portal';
import type {
    LeavePeriod,
    LeaveTypeOption,
    Paginated,
    PortalLeaveRequest,
} from '@/types';

/**
 * «Mis vacaciones» in the person's portal, in usted: the days they have by period, and what they have asked for. Only
 * days and dates, never an amount.
 */
export default function LeavePortal({
    token,
    company,
    periods,
    advanced,
    requests,
}: {
    token: string;
    company: string;
    name: string;
    /** Today as the company lives it, Y-m-d. */
    today: string;
    periods: LeavePeriod[];
    advanced: number;
    requests: Paginated<PortalLeaveRequest>;
    types: LeaveTypeOption[];
}) {
    return (
        <PortalShell company={company}>
            <Head title="Mis vacaciones" />
            <div className="flex w-full max-w-2xl flex-col gap-6">
                <Link
                    href={show.url({ token })}
                    className="touch-hit flex w-fit items-center gap-1.5 text-base font-medium text-primary"
                >
                    <ArrowLeft aria-hidden className="size-4" />
                    Volver a mi portal
                </Link>
                <div className="flex flex-col gap-2">
                    <h1 className="text-[26px] leading-[34px] font-semibold tracking-[-0.01em]">
                        Mis vacaciones
                    </h1>
                    <p className="text-muted-foreground">
                        Sus días de vacaciones y lo que ha pedido, como los
                        tiene registrados su empresa.
                    </p>
                </div>
                <PortalBalance periods={periods} advanced={advanced} />
                <PortalRequests requests={requests} />
            </div>
        </PortalShell>
    );
}
