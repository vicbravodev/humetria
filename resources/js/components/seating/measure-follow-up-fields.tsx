import Combobox from '@/components/humetria/combobox';
import DatePicker from '@/components/humetria/date-picker';
import FileDropZone from '@/components/humetria/file-drop-zone';
import InputError from '@/components/humetria/input-error';
import SeatChoiceField from '@/components/seating/seat-choice-field';
import { Label } from '@/components/ui/label';
import type { MeasureEditing, SeatOption } from '@/types';

type Errors = Partial<Record<string, string>>;

/** The follow-up of a measure: its status, who answers for it, the due date and the evidence. */
export default function MeasureFollowUpFields({
    editing,
    statuses,
    owners,
    errors,
}: {
    editing: MeasureEditing | null;
    statuses: SeatOption[];
    owners: SeatOption[];
    errors: Errors;
}) {
    return (
        <>
            <SeatChoiceField
                name="status"
                legend="Estado"
                options={statuses}
                value={editing?.status ?? 'planned'}
                error={errors['status']}
            />
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="measure-owner">Responsable</Label>
                    <Combobox
                        id="measure-owner"
                        name="owner_user_id"
                        source={{ options: owners }}
                        defaultValue={owners.find(
                            (owner) => owner.value === editing?.ownerUserId,
                        )}
                        placeholder="Elige a la persona"
                    />
                    <InputError message={errors['owner_user_id']} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="measure-due">Fecha compromiso</Label>
                    <DatePicker
                        id="measure-due"
                        name="due_on"
                        defaultValue={editing?.dueOn ?? undefined}
                    />
                    <InputError message={errors['due_on']} />
                </div>
            </div>
            <div className="grid gap-2">
                <p className="font-medium">
                    Evidencia
                    {editing?.hasEvidence === true && ' · ya tiene una'}
                </p>
                <FileDropZone
                    id="measure-evidence"
                    name="evidence"
                    accept="application/pdf,image/jpeg,image/png"
                    title="Arrastra la evidencia aquí o elígela"
                    hint="PDF, JPG o PNG"
                    invalid={errors['evidence'] !== undefined}
                    describedBy="measure-evidence-error"
                />
                <InputError
                    id="measure-evidence-error"
                    message={errors['evidence']}
                />
            </div>
        </>
    );
}
