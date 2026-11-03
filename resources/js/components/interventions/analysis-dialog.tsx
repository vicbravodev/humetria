import FormDialog from '@/components/interventions/form-dialog';
import TextField from '@/components/interventions/text-field';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { analysis } from '@/routes/tenant/campaigns/program/items';
import type { ProgramRow } from '@/types';

/** The analysis of a row: what Tablas 4 and 7 ask for each category and domain at Alto and Muy alto (II.4, III.4). */
export default function AnalysisDialog({
    open,
    onOpenChange,
    campaignId,
    row,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    campaignId: string;
    row: ProgramRow;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Análisis"
            description={`${row.label}. Qué explica el resultado y por qué se eligieron las acciones.`}
            form={analysis.form([tenantSlug, campaignId, row.id])}
            submit="Guardar análisis"
        >
            {(errors) => (
                <TextField
                    name="analysis"
                    label="Análisis"
                    defaultValue={row.analysis ?? ''}
                    error={errors['analysis']}
                    multiline
                />
            )}
        </FormDialog>
    );
}
