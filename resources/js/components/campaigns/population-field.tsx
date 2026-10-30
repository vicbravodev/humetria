import InputError from '@/components/input-error';
import { sampleSize } from '@/lib/campaigns';

/**
 * Who answers Guía III: everyone (the default) or the representative sample of III.1. Native radios with the design
 * system's look (RadioGroup): 20 px circle, primary dot.
 */
export default function PopulationField({
    workers,
    error,
}: {
    workers: number;
    error: string | undefined;
}) {
    const sample = sampleSize(workers);
    const options = [
        {
            value: 'all',
            label: 'Todas las personas',
            hint: `Las ${workers} personas del centro contestan la Guía III.`,
        },
        {
            value: 'sample',
            label: 'Una muestra representativa',
            hint: `Se sortean ${sample ?? 0} de ${workers} personas, en proporción por género, como indica la norma (III.1).`,
        },
    ];

    return (
        <fieldset
            className="grid gap-3"
            aria-describedby={
                error === undefined ? undefined : 'population-error'
            }
        >
            <legend className="mb-1 text-sm leading-none font-medium">
                ¿Quién contesta la Guía III?
            </legend>
            {options.map((option) => (
                <label
                    key={option.value}
                    className="flex cursor-pointer items-start gap-3 rounded-lg border p-3 has-checked:border-primary has-checked:bg-accent"
                >
                    <input
                        type="radio"
                        name="population"
                        value={option.value}
                        defaultChecked={option.value === 'all'}
                        className="mt-0.5 size-5 shrink-0 accent-primary"
                    />
                    <span className="grid gap-1">
                        <span className="text-sm font-medium">
                            {option.label}
                        </span>
                        <span className="text-sm text-muted-foreground">
                            {option.hint}
                        </span>
                    </span>
                </label>
            ))}
            <InputError id="population-error" role="alert" message={error} />
        </fieldset>
    );
}
