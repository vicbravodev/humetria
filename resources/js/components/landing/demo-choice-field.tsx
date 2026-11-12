import InputError from '@/components/humetria/input-error';
import DemoPill from '@/components/landing/demo-pill';

export type DemoChoice = {
    value: string;
    label: string;
};

type Props = {
    name: string;
    legend: string;
    options: readonly DemoChoice[];
    error?: string | undefined;
};

export default function DemoChoiceField({
    name,
    legend,
    options,
    error,
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
            <legend
                id={legendId}
                className="mb-2.5 text-meta leading-snug font-medium"
            >
                {legend}
            </legend>
            <div className="flex flex-wrap gap-2">
                {options.map((option) => (
                    <DemoPill
                        key={option.value}
                        type="radio"
                        name={name}
                        value={option.value}
                        label={option.label}
                    />
                ))}
            </div>
            <InputError id={errorId} message={error} />
        </fieldset>
    );
}
