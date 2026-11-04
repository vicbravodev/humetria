import { useState } from 'react';
import Combobox from '@/components/humetria/combobox';
import ChoiceField from '@/components/humetria/choice-field';
import DatePicker from '@/components/humetria/date-picker';
import FormDialog from '@/components/humetria/form-dialog';
import InputError from '@/components/humetria/input-error';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { ITEMS_57, MEDIA } from '@/lib/disclosure';
import { serverSource } from '@/lib/server-options';
import { options } from '@/routes/tenant/work-centers';
import { store } from '@/routes/tenant/disclosure/dissemination/records';

/**
 * Records a poster, bulletin, leaflet or meeting that put information in front of the people of a center: which, where, on
 * which day and which of the six things of 5.7 it covered (criterio C-52). Once recorded it cannot be changed: it is evidence.
 */
export default function RecordDialog({
    open,
    onOpenChange,
    today,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    today: string;
}) {
    const tenantSlug = useTenantSlug();
    const [medium, setMedium] = useState('poster');

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Registrar un medio"
            description="Un cartel, un boletín, un folleto o una reunión cuentan como evidencia de que las personas conocen la información (numeral 10.2). Una vez registrado no se puede cambiar."
            form={store.form(tenantSlug)}
            submit="Registrar"
        >
            {(errors) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="record-center">Centro de trabajo</Label>
                        <Combobox
                            id="record-center"
                            name="work_center_id"
                            source={serverSource(options.url(tenantSlug))}
                            placeholder="Busca un centro"
                            invalid={errors['work_center_id'] !== undefined}
                        />
                        <InputError message={errors['work_center_id']} />
                    </div>
                    <ChoiceField
                        name="medium"
                        legend="Cómo se dio a conocer"
                        options={MEDIA}
                        value={medium}
                        onChange={setMedium}
                        columns={2}
                        error={errors['medium']}
                    />
                    <fieldset className="grid gap-2">
                        <legend className="mb-1 text-sm font-medium">
                            Qué cubrió (numeral 5.7)
                        </legend>
                        {ITEMS_57.map(({ value, label }) => (
                            <label
                                key={value}
                                className="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg border border-input bg-card px-4 py-2.5 hover:bg-muted has-checked:border-primary has-checked:bg-accent has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring"
                            >
                                <input
                                    type="checkbox"
                                    name="covered_items[]"
                                    value={value}
                                    defaultChecked={value === 'a'}
                                    className="size-5 shrink-0 accent-primary"
                                />
                                <span>
                                    <span className="mr-1 font-medium">
                                        {value})
                                    </span>
                                    {label}
                                </span>
                            </label>
                        ))}
                        <InputError
                            message={
                                errors['covered_items'] ??
                                errors['covered_items.0']
                            }
                        />
                    </fieldset>
                    <div className="grid gap-2">
                        <Label htmlFor="record-day">Día</Label>
                        <DatePicker
                            id="record-day"
                            name="done_on"
                            defaultValue={today}
                            max={today}
                            invalid={errors['done_on'] !== undefined}
                        />
                        <InputError message={errors['done_on']} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="record-note">Nota (opcional)</Label>
                        <Textarea
                            id="record-note"
                            name="note"
                            aria-invalid={
                                errors['note'] === undefined ? undefined : true
                            }
                        />
                        <InputError message={errors['note']} />
                    </div>
                </>
            )}
        </FormDialog>
    );
}
