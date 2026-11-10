import { useState } from 'react';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import FormDialog from '@/components/humetria/form-dialog';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { SheetFooter } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { approve, cancel, reject } from '@/routes/tenant/vacations/requests';
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
    const route = { tenant: tenantSlug, leaveRequest: request.id };

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
                    form={cancel.form(route)}
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
                    <ConfirmDialog
                        trigger={<Button>Aprobar</Button>}
                        title={`¿Aprobar la solicitud de ${request.name}?`}
                        description="Se descuentan los días de su saldo y se le avisa por correo."
                        confirmLabel="Sí, aprobar"
                        dismissLabel="Volver"
                        destructive={false}
                        form={approve.form(route)}
                        preserveScroll
                    />
                    <FormDialog
                        open={rejecting}
                        onOpenChange={setRejecting}
                        title="Rechazar solicitud"
                        description={`${request.name} leerá el motivo que escribas.`}
                        form={reject.form(route)}
                        submit="Rechazar solicitud"
                        tone="destructive"
                    >
                        {(errors) => (
                            <div className="grid gap-2">
                                <Label htmlFor={`reject-${request.id}`}>
                                    Motivo del rechazo
                                </Label>
                                <Textarea
                                    id={`reject-${request.id}`}
                                    name="reason"
                                    rows={3}
                                    maxLength={500}
                                />
                                <InputError message={errors['reason']} />
                            </div>
                        )}
                    </FormDialog>
                </>
            )}
        </SheetFooter>
    );
}
