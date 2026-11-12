import { useState } from 'react';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import ApproveButton from '@/components/leave/approve-button';
import RejectForm from '@/components/leave/reject-form';
import { Button } from '@/components/ui/button';
import { SheetFooter } from '@/components/ui/sheet';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { cancel } from '@/routes/tenant/vacations/requests';
import type { LeaveQueueRequest } from '@/types';

/**
 * What RH can do with a request: answer one that waits (it may be before the manager does), or cancel one that holds its
 * dates, an enjoyed one included, which is how RH corrects. A viewer sees none of the buttons.
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

    return (
        <SheetFooter className="flex-row flex-wrap justify-end gap-2 border-t px-6 py-4">
            {request.canCancel && (
                <ConfirmDialog
                    trigger={
                        <Button variant="outline">Cancelar solicitud</Button>
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
            {request.canDecide && (
                <>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setRejecting(true)}
                    >
                        Rechazar
                    </Button>
                    <ApproveButton requestId={request.id} name={request.name} />
                    <RejectForm
                        requestId={request.id}
                        name={request.name}
                        open={rejecting}
                        onOpenChange={setRejecting}
                    />
                </>
            )}
        </SheetFooter>
    );
}
