import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dateSpan, statusTone, workingDaysOf } from '@/lib/leave/format';
import { cancel } from '@/routes/portal/vacations/requests';
import type { PortalLeaveRequest } from '@/types';

/** A request of the person: what they asked, for which days, where it stands, and the way to cancel it while they can. */
export default function RequestRow({
    token,
    request,
}: {
    token: string;
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
            {request.canCancel && (
                <ConfirmDialog
                    trigger={
                        <Button variant="outline" className="w-fit">
                            Cancelar solicitud
                        </Button>
                    }
                    title="¿Cancelar esta solicitud?"
                    description="Sus fechas quedan libres. Si ya estaba aprobada, los días vuelven a su saldo."
                    confirmLabel="Sí, cancelar"
                    dismissLabel="Volver"
                    form={cancel.form({ token, request: request.id })}
                    preserveScroll
                />
            )}
        </li>
    );
}
