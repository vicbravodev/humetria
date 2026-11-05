import { cn } from '@/lib/utils';

/** One answer among a few, each a card with what it means under it (board V4-Comp-Prestacion: ¿Cómo se paga?, el IMSS). */
export default function OptionCards({
    name,
    legend,
    options,
    value,
    columns,
    onChange,
}: {
    name: string;
    legend: string;
    options: { value: string; label: string; hint: string | undefined }[];
    value: string;
    columns: 1 | 2;
    onChange: (value: string) => void;
}) {
    return (
        <fieldset className="grid gap-2.5">
            <legend className="mb-2.5 font-medium">{legend}</legend>
            <div
                className={cn(
                    'grid gap-2.5',
                    columns === 2 && 'sm:grid-cols-2',
                )}
            >
                {options.map((option) => (
                    <label
                        key={option.value}
                        className="flex min-h-[52px] cursor-pointer items-start gap-3 rounded-lg border border-input bg-card px-4 py-3 transition-colors duration-100 ease-out hover:bg-muted has-checked:border-2 has-checked:border-primary has-checked:bg-accent has-checked:px-[15px] has-checked:py-[11px] has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring"
                    >
                        <input
                            type="radio"
                            name={name}
                            value={option.value}
                            checked={value === option.value}
                            onChange={() => onChange(option.value)}
                            className="mt-0.5 size-5 shrink-0 accent-primary"
                        />
                        <span className="grid gap-0.5">
                            <span className="font-medium">{option.label}</span>
                            {option.hint !== undefined && (
                                <span className="text-sm text-muted-foreground">
                                    {option.hint}
                                </span>
                            )}
                        </span>
                    </label>
                ))}
            </div>
        </fieldset>
    );
}
