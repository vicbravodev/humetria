import { cn } from '@/lib/utils';

/** One of a short fixed list, as choice cards: the checked one turns its border and fill to the accent. */
export default function ChoiceField<V extends string | number>({
    name,
    legend,
    options,
    value,
    onChange,
    error,
    columns = 1,
}: {
    name: string;
    legend: string;
    options: { value: V; label: string }[];
    value: V;
    onChange: (value: V) => void;
    error?: string | undefined;
    /** How many choices share a row from `sm` up. */
    columns?: 1 | 2 | 3;
}) {
    return (
        <fieldset className="grid gap-2">
            <legend className="mb-1 text-sm font-medium">{legend}</legend>
            <div
                className={cn(
                    'grid gap-2',
                    columns === 2 && 'sm:grid-cols-2',
                    columns === 3 && 'sm:grid-cols-3',
                )}
            >
                {options.map((option) => (
                    <label
                        key={option.value}
                        className="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg border border-input bg-card px-4 py-2.5 transition-colors duration-100 ease-out hover:bg-muted has-checked:border-2 has-checked:border-primary has-checked:bg-accent has-checked:px-[15px] has-checked:py-[9px] has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring"
                    >
                        <input
                            type="radio"
                            name={name}
                            value={option.value}
                            checked={option.value === value}
                            onChange={() => onChange(option.value)}
                            className="size-5 shrink-0 accent-primary"
                        />
                        {option.label}
                    </label>
                ))}
            </div>
            {error !== undefined && (
                <p role="alert" className="text-sm text-destructive">
                    {error}
                </p>
            )}
        </fieldset>
    );
}
