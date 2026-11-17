import { markName } from '@/lib/attendance/next-step';
import type { MarkType } from '@/lib/attendance/next-step';

const TYPES: MarkType[] = ['start', 'end', 'break_start', 'break_end'];

/** Which mark is missing (board «Asistencia: pedir una corrección»): four options of 48 px, two per row. */
export default function CorrectionTypeField({
    value,
    onChange,
}: {
    value: MarkType;
    onChange: (type: MarkType) => void;
}) {
    return (
        <fieldset className="grid grid-cols-2 gap-2">
            <legend className="col-span-2 mb-2 text-base font-medium">
                ¿Qué marca le faltó?
            </legend>
            {TYPES.map((type) => (
                <label key={type} className="relative block">
                    <input
                        type="radio"
                        name="mark_type"
                        value={type}
                        checked={value === type}
                        onChange={() => onChange(type)}
                        className="peer absolute inset-0 m-0 cursor-pointer opacity-0"
                    />
                    <span className="flex min-h-12 items-center justify-center rounded-lg border border-input bg-background px-2 text-center text-base transition-colors duration-150 ease-out peer-checked:border-2 peer-checked:border-primary peer-checked:bg-accent peer-checked:font-semibold peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-ring">
                        {markName(type)}
                    </span>
                </label>
            ))}
        </fieldset>
    );
}
