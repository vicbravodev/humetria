import { useState } from 'react';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import ApproveButton from '@/components/leave/approve-button';
import RejectForm from '@/components/leave/reject-form';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { approvalNote } from '@/lib/leave/queue';
import { cancel } from '@/routes/tenant/vacations/requests';
import type { LeaveQueueRequest } from '@/types';

/**
 * What RH can do with a request: answer one that waits («Aprobar» is the primary), or cancel one that holds its dates, an
 * enjoyed one included, which is how RH corrects. A viewer sees none of the buttons.
 */
export default function RequestActions({
    request,
}: {
    request: LeaveQueueRequest;
}) {
    const tenantSlug = useTenantSlug();
    const [rejecting, setRejecting] = useState(false);

    if (!request.canDecide && !request.canCancel) {
        return null;
    }

    const note = approvalNote(request);

    return (
        <div className="flex flex-wrap gap-2 max-sm:[&>*]:flex-1">
            {note !== null && (
                <p className="basis-full text-sm text-muted-foreground">
                    {note}
                </p>
            )}
            {request.canDecide && (
                <>
                    {request.canApprove && (
                        <ApproveButton
                            requestId={request.id}
                            name={request.name}
                            block
                        />
                    )}
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setRejecting(true)}
                        className="max-sm:h-11"
                    >
                        Rechazar
                    </Button>
                    <RejectForm
                        requestId={request.id}
                        name={request.name}
                        open={rejecting}
                        onOpenChange={setRejecting}
                    />
                </>
            )}
            {request.canCancel && (
                <ConfirmDialog
                    trigger={
                        <Button
                            variant={request.canDecide ? 'ghost' : 'outline'}
                            className="max-sm:h-11"
                        >
                            Cancelar solicitud
                        </Button>
                    }
                    title={`¿Cancelar la solicitud de ${request.name}?`}
                    description="Los días que haya usado regresan a su saldo. La solicitud queda en Canceladas."
                    confirmLabel="Sí, cancelar solicitud"
                    dismissLabel="Volver"
                    form={cancel.form({
                        tenant: tenantSlug,
                        leaveRequest: request.id,
                    })}
                    preserveScroll
                />
            )}
        </div>
    );
}
