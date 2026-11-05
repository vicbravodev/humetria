import InputError from '@/components/humetria/input-error';

/** The top of the scenario editor: the name typed as the title, and that nothing is saved until you say so. */
export default function ScenarioNameField({
    caption,
    defaultValue,
    error,
}: {
    caption: string;
    defaultValue: string;
    error: string | undefined;
}) {
    return (
        <header className="grid max-w-[760px] gap-2">
            <label
                htmlFor="scenario-name"
                className="text-caption font-semibold tracking-[0.06em] text-primary uppercase"
            >
                {caption}
            </label>
            <input
                id="scenario-name"
                name="name"
                maxLength={120}
                defaultValue={defaultValue}
                placeholder="Ponle un nombre, como Revisión 2027 · 6 %"
                autoComplete="off"
                aria-invalid={error === undefined ? undefined : true}
                aria-describedby={
                    error === undefined
                        ? 'scenario-name-lead'
                        : 'scenario-name-error'
                }
                className="min-h-14 w-full max-w-[560px] rounded-lg border border-input bg-card px-3.5 text-[28px] leading-9 font-semibold tracking-[-0.015em] placeholder:font-normal placeholder:text-muted-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring aria-invalid:border-destructive"
            />
            <InputError id="scenario-name-error" message={error} />
            <p
                id="scenario-name-lead"
                className="text-lg text-pretty text-muted-foreground"
            >
                Mueve los valores y mira el costo al instante. Nada se guarda
                hasta que tú lo decidas.
            </p>
        </header>
    );
}
