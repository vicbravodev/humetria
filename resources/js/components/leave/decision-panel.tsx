import Initials from '@/components/humetria/initials';
import RequestDetails from '@/components/leave/request-details';
import RequestActions from '@/components/leave/request-actions';
import TeamStrip from '@/components/leave/team-strip';
import { Badge } from '@/components/ui/badge';
import { dateSpan, statusTone, workingDaysOf } from '@/lib/leave/format';
import { coverageLine, reasonLine } from '@/lib/leave/overview';
import { daysLine } from '@/lib/leave/queue';
import type { LeaveQueueRequest } from '@/types';

/**
 * The decision panel of a request: who, which days, whether the days cover it and why it reached RH, who of the team is
 * out those days, and the answers. It replaces opening one request after another: RH reads and answers on the same screen.
 */
export default function DecisionPanel({
    request,
}: {
    request: LeaveQueueRequest;
}) {
    const waiting = request.status === 'requested';

    return (
        <article
            aria-label={`Solicitud de ${request.name}`}
            className="flex flex-col gap-5 card p-6"
        >
            <header className="flex items-start gap-3.5">
                <Initials name={request.name} className="size-10 text-sm" />
                <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                    <h2 className="text-lg leading-[26px] font-semibold">
                        {request.name}
                    </h2>
                    <p className="text-meta text-muted-foreground">
                        {request.type ?? 'Vacaciones'} ·{' '}
                        {dateSpan(request.startsOn, request.endsOn)} ·{' '}
                        {workingDaysOf(request.workingDays)}
                    </p>
                </div>
                {!waiting && (
                    <Badge variant={statusTone(request.status)}>
                        {request.statusLabel}
                    </Badge>
                )}
            </header>
            <div className="flex flex-col gap-1">
                <p className="font-medium">
                    {waiting && request.coverage !== null
                        ? coverageLine(request.coverage)
                        : daysLine(request)}
                </p>
                {request.reason !== null && (
                    <p className="text-meta text-muted-foreground">
                        {reasonLine(request.reason, request.manager)}
                    </p>
                )}
            </div>
            {request.team !== null && <TeamStrip team={request.team} />}
            <RequestDetails request={request} />
            <RequestActions request={request} />
        </article>
    );
}
