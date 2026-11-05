import { Camera } from 'lucide-react';
import FileDropZone from '@/components/humetria/file-drop-zone';
import InputError from '@/components/humetria/input-error';
import SettingRow from '@/components/humetria/setting-row';
import SeatChoiceField from '@/components/seating/seat-choice-field';
import { CHOICE_CLASS } from '@/lib/seating/choice-class';
import type { SeatEditing, SeatOption } from '@/types';

type Errors = Partial<Record<string, string>>;

/** Its condition, whether it is signposted (needed when it is away from the workstation) and a photo as evidence. */
export default function SeatStatusFields({
    seat,
    conditions,
    errors,
}: {
    seat: SeatEditing | null;
    conditions: SeatOption[];
    errors: Errors;
}) {
    return (
        <SettingRow
            icon={Camera}
            title="Estado, señalización y foto"
            description="La señalización se pide cuando el asiento está en un lugar distinto al puesto. La foto queda como evidencia y solo la ven RH y la Comisión del centro."
        >
            <div className="grid gap-5">
                <SeatChoiceField
                    name="condition"
                    legend="Estado"
                    options={conditions}
                    value={seat?.condition}
                    error={errors['condition']}
                />
                <label className={CHOICE_CLASS}>
                    <input type="hidden" name="signposted" value="0" />
                    <input
                        type="checkbox"
                        name="signposted"
                        value="1"
                        defaultChecked={seat?.signposted ?? false}
                        className="size-5 shrink-0 accent-primary"
                    />
                    El área está señalizada
                </label>
                <div className="grid gap-2">
                    <p className="font-medium">
                        Foto
                        {seat?.hasPhoto === true && ' · ya tiene una'}
                    </p>
                    <FileDropZone
                        id="seat-photo"
                        name="photo"
                        accept="image/jpeg,image/png"
                        title="Arrastra la foto aquí o elígela"
                        hint="JPG o PNG"
                        invalid={errors['photo'] !== undefined}
                        describedBy="seat-photo-error"
                    />
                    <InputError
                        id="seat-photo-error"
                        message={errors['photo']}
                    />
                </div>
            </div>
        </SettingRow>
    );
}
