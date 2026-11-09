import FormDialog from '@/components/humetria/form-dialog';
import InputError from '@/components/humetria/input-error';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { shortMoment } from '@/lib/attendance/corrections';
import { markName } from '@/lib/attendance/next-step';
import { approve, reject } from '@/routes/tenant/attendance/corrections';
import type { CorrectionRow } from '@/types';

/** RH approves a request (a note is optional) or rejects it with a note: the person reads it in «Mi semana». */
export default function CorrectionDialog({
    open,
    onOpenChange,
    correction,
    approving,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    correction: CorrectionRow;
    approving: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const route = { tenant: tenantSlug, correction: correction.id };
    const what = `${markName(correction.type)} a las ${shortMoment(correction.requestedAt)}`;

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={
                approving ? 'Aprobar la corrección' : 'Rechazar la solicitud'
            }
            description={
                approving
                    ? `${correction.person.name} queda con «${what}». La marca original se conserva y su semana se vuelve a confirmar.`
                    : `${correction.person.name} verá tu nota en «Mi semana». Su registro no cambia.`
            }
            form={approving ? approve.form(route) : reject.form(route)}
            submit={approving ? 'Aprobar' : 'Rechazar'}
            tone={approving ? 'default' : 'destructive'}
        >
            {(errors) => (
                <div className="grid gap-2">
                    <Label htmlFor={`note-${correction.id}`}>
                        {approving
                            ? 'Nota para la persona (opcional)'
                            : 'Por qué la rechazas'}
                    </Label>
                    <Textarea
                        id={`note-${correction.id}`}
                        name="note"
                        rows={3}
                        aria-invalid={errors['note'] !== undefined}
                    />
                    <InputError message={errors['note']} />
                </div>
            )}
        </FormDialog>
    );
}
