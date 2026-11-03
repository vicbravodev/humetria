import FormDialog from '@/components/interventions/form-dialog';
import ResponsibleFields from '@/components/interventions/responsible-fields';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { responsible } from '@/routes/tenant/campaigns/program';
import type { ProgramData, ProgramMember } from '@/types';

/** Who answers for the Programa (num. 8.4 f): the default of each action. A member also receives the reminders. */
export default function ResponsibleDialog({
    open,
    onOpenChange,
    campaignId,
    program,
    members,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    campaignId: string;
    program: ProgramData;
    members: ProgramMember[];
}) {
    const tenantSlug = useTenantSlug();

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Responsable del Programa"
            description="Cada acción nueva parte de este nombre. Si es una persona de la empresa, recibe los recordatorios."
            form={responsible.form([tenantSlug, campaignId])}
            submit="Guardar"
        >
            {(errors) => (
                <ResponsibleFields
                    members={members}
                    userId={program.responsible?.userId ?? null}
                    name={program.responsible?.name ?? ''}
                    errors={errors}
                />
            )}
        </FormDialog>
    );
}
