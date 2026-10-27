import InputError from '@/components/input-error';
import { cn } from '@/lib/utils';

export type DemoChoice = {
    value: string;
    label: string;
};

type Props = {
    name: string;
    legend: string;
    options: readonly DemoChoice[];
    error?: string | undefined;
    className?: string;
};

export default function DemoChoiceField({
    name,
    legend,
    options,
    error,
    className,
}: Props) {
    const legendId = `demo-${name}-legend`;
    const errorId = `demo-${name}-error`;

    return (
        <fieldset
            role="radiogroup"
            aria-labelledby={legendId}
            aria-required
            aria-invalid={error === undefined ? undefined : true}
            aria-describedby={error === undefined ? undefined : errorId}
            data-test={`${name}-group`}
            className="grid gap-2"
        >
            <legend id={legendId} className="mb-2 text-sm font-medium">
                {legend}
            </legend>
            <div className={cn('grid gap-2', className)}>
                {options.map((option) => (
                    <label
                        key={option.value}
                        className="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg border border-input bg-card px-3 py-2 text-base transition-[color,box-shadow] has-checked:border-primary has-checked:font-medium has-checked:ring-1 has-checked:ring-primary"
                    >
                        <input
                            type="radio"
                            name={name}
                            value={option.value}
                            className="size-5 shrink-0 accent-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        />
                        {option.label}
                    </label>
                ))}
            </div>
            <InputError id={errorId} message={error} />
        </fieldset>
    );
}
