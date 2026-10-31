import InputError from '@/components/input-error';
import { TOOLS } from '@/components/landing/landing-tools';

type Props = {
    error?: string | undefined;
};

export default function DemoToolsField({ error }: Props) {
    return (
        <fieldset
            aria-invalid={error === undefined ? undefined : true}
            aria-describedby={
                error === undefined ? undefined : 'demo-tools-error'
            }
            data-test="tools-group"
            className="grid gap-2"
        >
            <legend className="mb-2 text-sm font-medium">
                ¿Qué herramientas te interesan? (elige al menos una)
            </legend>
            <div className="grid gap-2 sm:grid-cols-3">
                {TOOLS.map((tool) => (
                    <label
                        key={tool.key}
                        className="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg border border-input bg-card px-3 py-2 text-base transition-[color,box-shadow] has-checked:border-primary has-checked:ring-1 has-checked:ring-primary"
                    >
                        <input
                            type="checkbox"
                            name="tools[]"
                            value={tool.key}
                            className="size-5 shrink-0 accent-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        />
                        <span className="font-medium">{tool.name}</span>
                    </label>
                ))}
            </div>
            <InputError id="demo-tools-error" role="alert" message={error} />
        </fieldset>
    );
}
