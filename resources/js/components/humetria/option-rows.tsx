import { cn } from '@/lib/utils';
import type { FlowAnswer, FlowOption } from '@/lib/question-flow';
import { toggled } from '@/lib/question-flow';

/**
 * The options of one question as 52 px rows (boards V2-Politica and V3-Doc-Riesgos): radios for one choice, checkboxes for
 * several. The chosen ones turn their border and fill to the accent.
 */
export default function OptionRows({
    name,
    legend,
    shown,
    options,
    multiple,
    columns,
    value,
    onChange,
}: {
    name: string;
    legend: string;
    /** Whether the legend is shown, or only read aloud. */
    shown: boolean;
    options: FlowOption[];
    multiple: boolean;
    columns: number;
    value: FlowAnswer;
    onChange: (value: string | string[]) => void;
}) {
    const chosen = Array.isArray(value) ? value : [];

    return (
        <fieldset className="grid gap-2.5">
            <legend className={shown ? 'mb-2.5 font-medium' : 'sr-only'}>
                {legend}
            </legend>
            <div
                className={cn(
                    'grid gap-2.5',
                    columns === 2 && 'sm:grid-cols-2',
                )}
            >
                {options.map((option) => (
                    <label
                        key={option.value}
                        className="flex min-h-[52px] cursor-pointer items-center gap-3 rounded-lg border border-input bg-card px-4 py-2.5 text-lg transition-colors duration-100 ease-out hover:bg-muted has-checked:border-2 has-checked:border-primary has-checked:bg-accent has-checked:px-[15px] has-checked:py-[9px] has-checked:font-medium has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring"
                    >
                        <input
                            type={multiple ? 'checkbox' : 'radio'}
                            name={name}
                            value={option.value}
                            checked={
                                multiple
                                    ? chosen.includes(option.value)
                                    : value === option.value
                            }
                            onChange={() =>
                                onChange(
                                    multiple
                                        ? toggled(chosen, option.value)
                                        : option.value,
                                )
                            }
                            className="size-5 shrink-0 accent-primary"
                        />
                        {option.label}
                    </label>
                ))}
            </div>
        </fieldset>
    );
}
