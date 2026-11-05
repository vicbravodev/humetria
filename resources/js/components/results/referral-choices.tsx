import { cn } from '@/lib/utils';

/**
 * One choice of the channeling as cards with a name and a line under it (board V4-N035-Canalizacion), two to a row from
 * `sm` up. A choice that cannot be taken says why under its name.
 */
export default function ReferralChoices<V extends string>({
    name,
    legend,
    options,
    value,
    onChange,
    columns,
}: {
    name: string;
    legend: string;
    options: { value: V; label: string; hint: string; disabled?: boolean }[];
    value: V | null;
    onChange: (value: V) => void;
    columns: 1 | 2;
}) {
    return (
        <fieldset className="grid gap-2.5">
            <legend className="mb-3 text-lg font-semibold">{legend}</legend>
            <div
                className={cn(
                    'grid gap-2.5',
                    columns === 2 && 'sm:grid-cols-2',
                )}
            >
                {options.map((option) => (
                    <label
                        key={option.value}
                        className={cn(
                            'flex min-h-[64px] items-start gap-3 rounded-[10px] border border-input bg-card px-4 py-3 transition-colors duration-100 ease-out',
                            'has-checked:border-2 has-checked:border-primary has-checked:bg-accent has-checked:px-[15px] has-checked:py-[11px] has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring',
                            option.disabled === true
                                ? 'cursor-not-allowed opacity-60'
                                : 'cursor-pointer hover:bg-muted',
                        )}
                    >
                        <input
                            type="radio"
                            name={name}
                            value={option.value}
                            checked={value === option.value}
                            disabled={option.disabled}
                            onChange={() => onChange(option.value)}
                            className="mt-1 size-[18px] shrink-0 accent-primary"
                        />
                        <span className="min-w-0">
                            <span className="block font-medium">
                                {option.label}
                            </span>
                            <span className="block text-sm text-muted-foreground">
                                {option.hint}
                            </span>
                        </span>
                    </label>
                ))}
            </div>
        </fieldset>
    );
}
