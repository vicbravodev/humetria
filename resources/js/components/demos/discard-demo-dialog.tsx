import FormDialog from '@/components/humetria/form-dialog';
import InputError from '@/components/humetria/input-error';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { discard } from '@/routes/demo-requests';
import type { DemoRequestRow } from '@/types';

/** Closes a request with the reason, which only the team reads. */
export default function DiscardDemoDialog({
    request,
    open,
    onOpenChange,
}: {
    request: DemoRequestRow;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Descartar solicitud"
            description={`${request.company} deja el embudo. Puedes volver a moverla después.`}
            form={discard.form(request.id)}
            submit="Descartar"
            tone="destructive"
        >
            {(errors) => (
                <div className="grid gap-2">
                    <Label htmlFor={`reason-${request.id}`}>Motivo</Label>
                    <Textarea
                        id={`reason-${request.id}`}
                        name="reason"
                        maxLength={500}
                        required
                        placeholder="Sin presupuesto este año, buscaba otra cosa…"
                    />
                    <InputError message={errors['reason']} />
                </div>
            )}
        </FormDialog>
    );
}
