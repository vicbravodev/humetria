import { useState } from 'react';
import ChoiceField from '@/components/humetria/choice-field';
import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/humetria/input-error';
import FormDialog from '@/components/interventions/form-dialog';
import TextField from '@/components/interventions/text-field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { nextStatuses } from '@/lib/action-form';
import { store } from '@/routes/tenant/campaigns/program/progress';
import type { ActionStatusValue, ProgramAction } from '@/types';

/**
 * A step of an action (num. 8.4 d): the state it moves to, the day and a note, with a file of evidence if there is one
 * (a call-up, an attendance list). The log is never edited: a mistake is answered with another step.
 */
export default function ProgressDialog({
    open,
    onOpenChange,
    campaignId,
    action,
    today,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    campaignId: string;
    action: ProgramAction;
    today: string;
}) {
    const tenantSlug = useTenantSlug();
    const options = nextStatuses(action.status.value);
    const [status, setStatus] = useState<ActionStatusValue>(
        options[0]?.value ?? 'in_progress',
    );

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Registrar avance"
            description={`${action.title}. Cada avance queda en el historial y no se edita.`}
            form={store.form([tenantSlug, campaignId, action.id])}
            submit="Registrar avance"
        >
            {(errors) => (
                <>
                    <ChoiceField
                        name="status"
                        legend="Pasa a"
                        options={options}
                        value={status}
                        onChange={setStatus}
                        error={errors['status']}
                        columns={2}
                    />
                    <div className="grid gap-2">
                        <Label htmlFor="progress-day">
                            {status === 'done' ? 'Fecha de cierre' : 'Fecha'}
                        </Label>
                        <DatePicker
                            id="progress-day"
                            name="recorded_on"
                            defaultValue={today}
                            invalid={errors['recorded_on'] !== undefined}
                        />
                        <InputError message={errors['recorded_on']} />
                    </div>
                    <TextField
                        name="note"
                        label="Nota (opcional)"
                        error={errors['note']}
                        multiline
                    />
                    <div className="grid gap-2">
                        <Label htmlFor="progress-evidence">
                            Evidencia (PDF, JPG o PNG)
                        </Label>
                        <Input
                            id="progress-evidence"
                            name="evidence"
                            type="file"
                            accept="application/pdf,image/jpeg,image/png"
                        />
                        <p className="text-sm text-muted-foreground">
                            Las listas de asistencia llevan nombres: solo el
                            titular y RH abren el archivo, y no se imprime en el
                            informe.
                        </p>
                        <InputError message={errors['evidence']} />
                    </div>
                </>
            )}
        </FormDialog>
    );
}
