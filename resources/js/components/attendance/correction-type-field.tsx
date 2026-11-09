import { Label } from '@/components/ui/label';
import { isMarkType, markName } from '@/lib/attendance/next-step';
import type { MarkType } from '@/lib/attendance/next-step';

const TYPES: MarkType[] = ['start', 'break_start', 'break_end', 'end'];

/** Which mark is missing: entrada, salida a comer, regreso de comer or salida. */
export default function CorrectionTypeField({
    value,
    onChange,
}: {
    value: MarkType;
    onChange: (type: MarkType) => void;
}) {
    return (
        <div className="flex flex-col gap-2">
            <Label htmlFor="correction-type" className="text-base">
                ¿Qué marca le faltó?
            </Label>
            <select
                id="correction-type"
                name="mark_type"
                value={value}
                onChange={(event) => {
                    if (isMarkType(event.target.value)) {
                        onChange(event.target.value);
                    }
                }}
                className="h-11 w-full rounded-lg border border-input bg-card px-3 text-base focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            >
                {TYPES.map((type) => (
                    <option key={type} value={type}>
                        {markName(type)}
                    </option>
                ))}
            </select>
        </div>
    );
}
