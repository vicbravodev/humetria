import FormDialog from '@/components/humetria/form-dialog';
import InputError from '@/components/humetria/input-error';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { reject } from '@/routes/tenant/vacations/requests';

/** The reason RH writes when it rejects a request: the person reads it. It opens from whoever holds `open`. */
export default function RejectForm({
    requestId,
    name,
    open,
    onOpenChange,
}: {
    requestId: string;
    name: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Rechazar solicitud"
            description={`${name} leerá el motivo que escribas.`}
            form={reject.form({ tenant: tenantSlug, leaveRequest: requestId })}
            submit="Rechazar solicitud"
            tone="destructive"
        >
            {(errors) => (
                <div className="grid gap-2">
                    <Label htmlFor={`reject-${requestId}`}>
                        Motivo del rechazo
                    </Label>
                    <Textarea
                        id={`reject-${requestId}`}
                        name="reason"
                        rows={3}
                        maxLength={500}
                    />
                    <InputError message={errors['reason']} />
                </div>
            )}
        </FormDialog>
    );
}
