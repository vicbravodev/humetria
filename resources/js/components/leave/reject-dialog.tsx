import FormDialog from '@/components/humetria/form-dialog';
import InputError from '@/components/humetria/input-error';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { reject } from '@/routes/portal/vacations/team';
import type { TeamLeaveRequest } from '@/types';

/** The manager rejects a request: the reason is required, and the person who asked reads it. */
export default function RejectDialog({
    token,
    request,
    open,
    onOpenChange,
}: {
    token: string;
    request: TeamLeaveRequest;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const id = `reject-reason-${request.id}`;

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Rechazar solicitud"
            description={`${request.name} leerá el motivo que usted escriba.`}
            form={reject.form({ token, request: request.id })}
            submit="Rechazar solicitud"
            tone="destructive"
        >
            {(errors) => (
                <div className="grid gap-2">
                    <Label htmlFor={id}>Motivo del rechazo</Label>
                    <Textarea
                        id={id}
                        name="reason"
                        rows={3}
                        maxLength={500}
                        aria-invalid={
                            errors['reason'] === undefined ? undefined : true
                        }
                    />
                    <InputError message={errors['reason']} />
                </div>
            )}
        </FormDialog>
    );
}
