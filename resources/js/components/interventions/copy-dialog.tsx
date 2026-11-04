import InputError from '@/components/humetria/input-error';
import FormDialog from '@/components/interventions/form-dialog';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { copy } from '@/routes/tenant/campaigns/program/actions';
import type { ProgramAction, ProgramRow } from '@/types';

/**
 * An action is repeated in other rows as independent copies (planned, with no history): each is followed and reported by
 * itself, and none is linked to several rows.
 */
export default function CopyDialog({
    open,
    onOpenChange,
    campaignId,
    action,
    rows,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    campaignId: string;
    action: ProgramAction;
    rows: ProgramRow[];
}) {
    const tenantSlug = useTenantSlug();

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Copiar a otras filas"
            description={`${action.title}. Cada copia empieza planeada y sin avances.`}
            form={copy.form([tenantSlug, campaignId, action.id])}
            submit="Copiar"
        >
            {(errors) => (
                <fieldset className="grid max-h-72 gap-2 overflow-y-auto">
                    <legend className="mb-1 text-sm font-medium">Filas</legend>
                    {rows
                        .filter((row) => row.id !== action.itemId)
                        .map((row) => (
                            <label
                                key={row.id}
                                className="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg border border-input bg-card px-4 py-2.5 hover:bg-muted has-checked:border-primary has-checked:bg-accent has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring"
                            >
                                <input
                                    type="checkbox"
                                    name="item_ids[]"
                                    value={row.id}
                                    className="size-5 shrink-0 accent-primary"
                                />
                                {row.label}
                            </label>
                        ))}
                    <InputError message={errors['item_ids']} />
                </fieldset>
            )}
        </FormDialog>
    );
}
