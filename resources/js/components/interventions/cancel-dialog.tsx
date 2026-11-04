import FormDialog from '@/components/interventions/form-dialog';
import TextField from '@/components/interventions/text-field';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { cancel } from '@/routes/tenant/campaigns/program/actions';
import type { ProgramAction } from '@/types';

/** An action is cancelled with its reason and stays in the Programa as «cancelada»: nothing of it is deleted (num. 10.4). */
export default function CancelDialog({
    open,
    onOpenChange,
    campaignId,
    action,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    campaignId: string;
    action: ProgramAction;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Cancelar la acción"
            description={`${action.title}. No se borra: queda en el Programa como evidencia, con su motivo.`}
            form={cancel.form([tenantSlug, campaignId, action.id])}
            submit="Cancelar la acción"
            tone="destructive"
        >
            {(errors) => (
                <TextField
                    name="reason"
                    label="Motivo"
                    error={errors['reason']}
                    multiline
                />
            )}
        </FormDialog>
    );
}
