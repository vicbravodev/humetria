import { useState } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const CARD =
    'flex min-h-12 flex-[1_1_120px] cursor-pointer items-center gap-3 rounded-lg border border-input bg-card px-4 py-2.5 transition-colors duration-100 ease-out hover:bg-muted has-checked:border-2 has-checked:border-primary has-checked:bg-accent has-checked:px-[15px] has-checked:py-[9px] has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring';

/**
 * The months between applications of Section II (criterio C-14): the usual periods as option cards, or "Otro" with
 * its own number. What is sent is always `period_months`.
 */
export default function ReviewPeriodField({
    presets,
    max,
    defaultMonths,
    error,
}: {
    presets: number[];
    max: number;
    defaultMonths: number;
    error: string | undefined;
}) {
    const [custom, setCustom] = useState(!presets.includes(defaultMonths));
    const [months, setMonths] = useState(String(defaultMonths));

    return (
        <fieldset
            className="flex flex-col gap-3"
            aria-describedby={error === undefined ? undefined : 'period-error'}
        >
            <legend className="sr-only">Periodicidad de la Sección II</legend>
            <input type="hidden" name="period_months" value={months} />
            <div className="flex flex-wrap items-stretch gap-2.5">
                {presets.map((preset) => (
                    <label key={preset} className={CARD}>
                        <input
                            type="radio"
                            name="period_choice"
                            checked={!custom && months === String(preset)}
                            onChange={() => {
                                setCustom(false);
                                setMonths(String(preset));
                            }}
                            className="size-5 shrink-0 accent-primary"
                        />
                        <span className="font-medium">{preset} meses</span>
                    </label>
                ))}
                <label className={CARD}>
                    <input
                        type="radio"
                        name="period_choice"
                        checked={custom}
                        onChange={() => setCustom(true)}
                        className="size-5 shrink-0 accent-primary"
                    />
                    <span className="font-medium">Otro</span>
                </label>
            </div>
            {custom && (
                <div className="grid max-w-60 gap-2">
                    <Label htmlFor="period-custom">Cada cuántos meses</Label>
                    <Input
                        id="period-custom"
                        type="number"
                        inputMode="numeric"
                        min={1}
                        max={max}
                        step={1}
                        value={months}
                        onChange={(event) => setMonths(event.target.value)}
                        aria-invalid={error === undefined ? undefined : true}
                    />
                </div>
            )}
            <InputError id="period-error" role="alert" message={error} />
        </fieldset>
    );
}
