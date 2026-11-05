import { cn } from '@/lib/utils';

/**
 * A short question answered with pills in a row (board V3-Onboarding: Sí, No; the sector): native radios, so the arrows
 * move between them, with a 44 px touch target. The chosen one takes the accent.
 */
export default function PillChoice<V extends string>({
    name,
    legend,
    options,
    value,
    onChange,
}: {
    name: string;
    legend: string;
    options: { value: V; label: string }[];
    value: V | null;
    onChange: (value: V) => void;
}) {
    return (
        <fieldset className="flex flex-col gap-2.5">
            <legend className="mb-2.5 font-medium">{legend}</legend>
            <div className="flex flex-wrap gap-2">
                {options.map((option) => (
                    <label
                        key={option.value}
                        className={cn(
                            'inline-flex min-h-11 cursor-pointer items-center rounded-lg border border-input bg-card px-4 transition-colors duration-100 ease-out hover:bg-muted',
                            'has-checked:border-2 has-checked:border-primary has-checked:bg-accent has-checked:px-[15px] has-checked:font-medium',
                            'has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring',
                        )}
                    >
                        <input
                            type="radio"
                            name={name}
                            value={option.value}
                            checked={option.value === value}
                            onChange={() => onChange(option.value)}
                            className="sr-only"
                        />
                        {option.label}
                    </label>
                ))}
            </div>
        </fieldset>
    );
}
