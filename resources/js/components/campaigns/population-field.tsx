import InputError from '@/components/input-error';
import { sampleSize } from '@/lib/campaigns';

/**
 * Who answers Guía III: the representative sample of III.1 (the default) or everyone. Native radios drawn as the
 * design system's options (pantalla NOM035-Nueva): 20 px circle, 2 px primary border on the chosen one.
 */
export default function PopulationField({
    workers,
    error,
}: {
    workers: number;
    error: string | undefined;
}) {
    const options = [
        {
            value: 'sample',
            label: `Muestra representativa · ${sampleSize(workers) ?? 0} personas`,
            hint: `Humetría la sortea en proporción por género, como pide la norma (III.1). La Guía I llega a las ${workers}.`,
        },
        {
            value: 'all',
            label: `Todas las personas · ${workers}`,
            hint: 'Más respuestas y más tiempo de tu gente.',
        },
    ];

    return (
        <fieldset
            className="flex flex-col gap-2.5"
            aria-describedby={
                error === undefined ? undefined : 'population-error'
            }
        >
            <legend className="mb-2.5 text-sm leading-5 font-medium">
                A quién se aplica la Guía III
            </legend>
            {options.map((option) => (
                <label
                    key={option.value}
                    className="flex min-h-14 cursor-pointer items-start gap-3 rounded-lg border border-input bg-card px-4 py-3 transition-colors duration-150 ease-out hover:bg-muted has-checked:border-2 has-checked:border-primary has-checked:bg-accent has-checked:px-[15px] has-checked:py-[11px] has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring"
                >
                    <input
                        type="radio"
                        name="population"
                        value={option.value}
                        defaultChecked={option.value === 'sample'}
                        className="peer sr-only"
                    />
                    <span
                        aria-hidden
                        className="mt-0.5 inline-flex size-5 shrink-0 items-center justify-center rounded-full border-[1.5px] border-input bg-card peer-checked:border-2 peer-checked:border-primary peer-checked:after:size-2.5 peer-checked:after:rounded-full peer-checked:after:bg-primary peer-checked:after:content-['']"
                    />
                    <span className="flex grow flex-col gap-0.5">
                        <span className="font-medium">{option.label}</span>
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
