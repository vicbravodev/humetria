import { Badge } from '@/components/ui/badge';
import { dateSpan, statusTone, workingDaysOf } from '@/lib/leave/format';
import type { PortalLeaveRequest } from '@/types';

/** A request of the person: what they asked, for which days, and where it stands. */
export default function RequestRow({
    request,
}: {
    request: PortalLeaveRequest;
}) {
    return (
        <li className="flex flex-col gap-2 p-4">
            <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                <p className="text-base font-semibold">
                    {request.type ?? 'Vacaciones'}
                </p>
                <Badge variant={statusTone(request.status)}>
                    {request.statusLabel}
                </Badge>
            </div>
            <p className="text-base text-muted-foreground">
                {dateSpan(request.startsOn, request.endsOn)} ·{' '}
                {workingDaysOf(request.workingDays)}
            </p>
            {request.hadBalance === false && request.status === 'requested' && (
                <p className="text-base text-muted-foreground">
                    No tenía días disponibles al pedirla; quien la apruebe lo
                    verá.
                </p>
            )}
            {request.rejectionReason !== null && (
                <p className="text-base">Motivo: {request.rejectionReason}</p>
            )}
        </li>
    );
}
