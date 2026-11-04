import { useState } from 'react';
import Combobox from '@/components/humetria/combobox';
import InputError from '@/components/humetria/input-error';
import FormDialog from '@/components/interventions/form-dialog';
import { Label } from '@/components/ui/label';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { SCOPE_LABELS } from '@/lib/interventions';
import { store } from '@/routes/tenant/campaigns/program/items';
import type { ManualOption } from '@/types';

/**
 * A row HR adds: a category or domain that did not come out in risk (a preventive action, num. 8.1 and 8.2) or, in a sample
 * under the minimum, whichever it wants. It has no level and no figures, and it does not count toward what the Tabla asks.
 */
export default function AddRowDialog({
    open,
    onOpenChange,
    campaignId,
    options,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    campaignId: string;
    options: ManualOption[];
}) {
    const tenantSlug = useTenantSlug();
    const [chosen, setChosen] = useState<ManualOption | undefined>(undefined);
    const choices = options.map((option) => ({
        value: `${option.scope}:${option.key}`,
        label: `${SCOPE_LABELS[option.scope]} · ${option.label}`,
    }));

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Agregar fila"
            description="Elige de la lista de la guía. La fila no lleva nivel ni cifras: nace de tu decisión, no del resultado."
            form={store.form([tenantSlug, campaignId])}
            submit="Agregar fila"
        >
            {(errors) => (
                <div className="grid gap-2">
                    <Label htmlFor="add-row">Categoría o dominio</Label>
                    <input
                        type="hidden"
                        name="scope"
                        value={chosen?.scope ?? ''}
                    />
                    <input
                        type="hidden"
                        name="scope_key"
                        value={chosen?.key ?? ''}
                    />
                    <Combobox
                        id="add-row"
                        name="choice"
                        source={{ options: choices }}
                        placeholder="Elige de la lista"
                        onChange={(choice) =>
                            setChosen(
                                options.find(
                                    (option) =>
                                        `${option.scope}:${option.key}` ===
                                        choice.value,
                                ),
                            )
                        }
                    />
                    <InputError
                        message={errors['scope_key'] ?? errors['scope']}
                    />
                </div>
            )}
        </FormDialog>
    );
}
