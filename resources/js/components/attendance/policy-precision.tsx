import InputError from '@/components/humetria/input-error';
import { Label } from '@/components/ui/label';
import { precisionNote } from '@/lib/attendance/policy-preview';

/** How exact a phone's location must be to count as inside a zone: a slider with its meters read out and what they mean. */
export default function PolicyPrecision({
    value,
    onChange,
    error,
}: {
    value: number;
    onChange: (meters: number) => void;
    error: string | undefined;
}) {
    return (
        <div className="flex flex-col gap-2.5 rounded-lg border bg-card p-4">
            <div className="flex items-baseline justify-between gap-4">
                <Label htmlFor="policy-max_accuracy_m">
                    Precisión máxima (de 10 a 500 metros)
                </Label>
                <output
                    htmlFor="policy-max_accuracy_m"
                    className="text-2xl leading-8 font-semibold tabular-nums"
                >
                    {value} m
                </output>
            </div>
            <input
                id="policy-max_accuracy_m"
                name="max_accuracy_m"
                type="range"
                min={10}
                max={500}
                step={1}
                value={value}
                onChange={(event) => onChange(Number(event.target.value))}
                aria-invalid={error === undefined ? undefined : true}
                className="h-11 w-full cursor-pointer accent-primary disabled:cursor-not-allowed"
            />
            <div className="flex justify-between text-sm text-muted-foreground">
                <span>10 m · más estricta</span>
                <span>500 m · más flexible</span>
            </div>
            <p className="text-sm text-pretty">{precisionNote(value)}</p>
            <InputError message={error} />
        </div>
    );
}
