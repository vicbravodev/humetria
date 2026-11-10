import { useState } from 'react';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import Initials from '@/components/humetria/initials';
import RejectDialog from '@/components/leave/reject-dialog';
import { Button } from '@/components/ui/button';
import { balanceLine, dateSpan, workingDaysOf } from '@/lib/leave/format';
import { approve } from '@/routes/portal/vacations/team';
import type { TeamLeaveRequest } from '@/types';

/**
 * A request that waits for the manager: who asks, for what and which days, where the days would come from, and the two
 * answers. «Aprobar» is not offered for vacations the person has no days for when their policy gives none in advance.
 */
export default function DecisionCard({
    token,
    request,
}: {
    token: string;
    request: TeamLeaveRequest;
}) {
    const [rejecting, setRejecting] = useState(false);
    const what = request.type ?? 'Vacaciones';
    const span = `${dateSpan(request.startsOn, request.endsOn)} · ${workingDaysOf(request.workingDays)}`;

    return (
        <li className="flex flex-col gap-4 card p-5">
            <div className="flex items-center gap-3.5">
                <Initials name={request.name} className="size-10 text-sm" />
                <p className="flex min-w-0 flex-col">
                    <span className="text-base font-semibold">
                        {request.name}
                    </span>
                    <span className="text-base text-muted-foreground">
                        {what}
                    </span>
                </p>
            </div>
            <div className="flex flex-col gap-1">
                <p className="font-medium">{span}</p>
                <p className="text-base text-muted-foreground">
                    {balanceLine(request)}
                </p>
                {request.note !== null && (
                    <p className="text-base">Nota: {request.note}</p>
                )}
            </div>
            <div className="flex flex-col gap-2 sm:flex-row">
                {request.canApprove && (
                    <ConfirmDialog
                        trigger={<Button size="lg">Aprobar</Button>}
                        title={`¿Aprobar la solicitud de ${request.name}?`}
                        description={`${what}: ${span}. Le avisaremos por correo.`}
                        confirmLabel="Sí, aprobar"
                        dismissLabel="Volver"
                        destructive={false}
                        form={approve.form({ token, request: request.id })}
                        preserveScroll
                    />
                )}
                <Button
                    type="button"
                    size="lg"
                    variant="outline"
                    onClick={() => setRejecting(true)}
                >
                    Rechazar
                </Button>
            </div>
            <RejectDialog
                token={token}
                request={request}
                open={rejecting}
                onOpenChange={setRejecting}
            />
        </li>
    );
}
