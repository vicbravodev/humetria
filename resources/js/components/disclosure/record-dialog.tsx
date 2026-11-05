import { useState } from 'react';
import CoveredItemsField from '@/components/disclosure/covered-items-field';
import EvidenceField from '@/components/disclosure/evidence-field';
import Combobox from '@/components/humetria/combobox';
import ChoiceField from '@/components/humetria/choice-field';
import DatePicker from '@/components/humetria/date-picker';
import FormDialog from '@/components/humetria/form-dialog';
import InputError from '@/components/humetria/input-error';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { MEDIA } from '@/lib/disclosure';
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
    initialMedium = 'poster',
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    today: string;
    /** The one chosen from «Registrar otro» (board V4-Difusion-Registro). */
    initialMedium?: string;
}) {
    const tenantSlug = useTenantSlug();
    const [medium, setMedium] = useState(initialMedium);

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Registrar un medio"
            description="Un cartel, un boletín, un folleto o una reunión cuentan como evidencia de que las personas conocen la información (numeral 10.2). Eliges uno, subes una foto o un PDF y marcas lo que cubrió. Una vez registrado no se puede cambiar."
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
                    <CoveredItemsField
                        error={
                            errors['covered_items'] ?? errors['covered_items.0']
                        }
                    />
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
                    <EvidenceField
                        id="record-evidence"
                        label="Foto o PDF (opcional)"
                        title="Arrastra la foto o el PDF aquí o elígelo"
                        hint="PDF, JPG o PNG"
                        error={errors['evidence']}
                    />
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
