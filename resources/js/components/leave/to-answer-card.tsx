import { X } from 'lucide-react';
import { useState } from 'react';
import Initials from '@/components/humetria/initials';
import RowMenu from '@/components/humetria/row-menu';
import ApproveButton from '@/components/leave/approve-button';
import RejectForm from '@/components/leave/reject-form';
import { Badge } from '@/components/ui/badge';
import { dateSpan, workingDaysOf } from '@/lib/leave/format';
import { coverageLine, reasonLine, startsIn } from '@/lib/leave/overview';
import type { LeaveQueueRequest } from '@/types';

/**
 * A request that waits for RH, as a card to answer from the overview: who, which days, why it reached RH and whether the
 * days cover it. «Aprobar» is the primary only on the first card; «Rechazar» is in the menu of the card.
 */
export default function ToAnswerCard({
    request,
    today,
    primary,
}: {
    request: LeaveQueueRequest;
    today: string;
    primary: boolean;
}) {
    const [rejecting, setRejecting] = useState(false);
    const soon = startsIn(request.startsOn, today);

    return (
        <li className="flex flex-col gap-4 card p-5 sm:flex-row sm:items-center">
            <Initials name={request.name} className="size-10 text-sm" />
            <div className="flex min-w-0 flex-1 flex-col gap-1">
                <p className="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <span className="font-semibold">{request.name}</span>
                    {soon !== null && <Badge variant="warning">{soon}</Badge>}
                </p>
                <p className="text-meta">
                    {request.type ?? 'Vacaciones'} ·{' '}
                    {dateSpan(request.startsOn, request.endsOn)} ·{' '}
                    {workingDaysOf(request.workingDays)}
                </p>
                <p className="text-meta text-muted-foreground">
                    {reasonLine(request.reason, request.manager)}
                </p>
                {request.coverage !== null && (
                    <p className="text-meta text-muted-foreground">
                        {coverageLine(request.coverage)}
                    </p>
                )}
            </div>
            {request.canDecide && (
                <div className="flex items-center gap-1 max-sm:justify-between">
                    <ApproveButton
                        requestId={request.id}
                        name={request.name}
                        primary={primary}
                        block
                    />
                    <RowMenu
                        label={`Acciones de ${request.name}`}
                        items={[
                            {
                                label: 'Rechazar',
                                icon: X,
                                onSelect: () => setRejecting(true),
                            },
                        ]}
                    />
                    <RejectForm
                        requestId={request.id}
                        name={request.name}
                        open={rejecting}
                        onOpenChange={setRejecting}
                    />
                </div>
            )}
        </li>
    );
}
