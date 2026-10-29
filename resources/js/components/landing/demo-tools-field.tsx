import InputError from '@/components/input-error';

const TOOLS = [
    { value: 'nom_035', label: 'NOM-035', hint: 'Riesgo psicosocial' },
    { value: 'nom_037', label: 'NOM-037', hint: 'Teletrabajo' },
    { value: 'ley_silla', label: 'Ley Silla', hint: 'Asientos y descansos' },
] as const;

type Props = {
    error?: string | undefined;
};

export default function DemoToolsField({ error }: Props) {
    return (
        <fieldset
            role="group"
            aria-labelledby="demo-tools-legend"
            aria-invalid={error === undefined ? undefined : true}
            aria-describedby={
                error === undefined ? undefined : 'demo-tools-error'
            }
            data-test="tools-group"
            className="grid gap-2"
        >
            <legend id="demo-tools-legend" className="mb-2 text-sm font-medium">
                ¿Qué herramientas te interesan?
            </legend>
            <div className="grid gap-2 sm:grid-cols-3">
                {TOOLS.map((tool) => (
                    <label
                        key={tool.value}
                        className="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg border border-input bg-card px-3 py-2 text-base transition-[color,box-shadow] has-checked:border-primary has-checked:ring-1 has-checked:ring-primary"
                    >
                        <input
                            type="checkbox"
                            name="tools[]"
                            value={tool.value}
                            className="size-5 shrink-0 accent-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        />
                        <span className="grid">
                            <span className="font-medium">{tool.label}</span>
                            <span className="text-sm text-muted-foreground">
                                {tool.hint}
                            </span>
                        </span>
                    </label>
                ))}
            </div>
            <InputError id="demo-tools-error" role="alert" message={error} />
        </fieldset>
    );
}
