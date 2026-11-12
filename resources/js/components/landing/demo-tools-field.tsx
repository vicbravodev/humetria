import InputError from '@/components/humetria/input-error';
import DemoPill from '@/components/landing/demo-pill';
import { FAMILIES, familyTools } from '@/components/landing/landing-families';

type Props = {
    error?: string | undefined;
};

/** The tools to show in the demo, grouped by family as the tools section shows them. */
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
            <legend className="mb-2.5 text-meta leading-snug font-medium">
                ¿Qué herramientas te interesan?{' '}
                <span className="font-normal text-muted-foreground">
                    (elige al menos una)
                </span>
            </legend>
            <div className="grid gap-3.5">
                {FAMILIES.map((family) => (
                    <div key={family.key} className="grid gap-2">
                        <p className="text-caption text-muted-foreground">
                            {family.name}
                        </p>
                        <div className="flex flex-wrap gap-2">
                            {familyTools(family).map((tool) => (
                                <DemoPill
                                    key={tool.key}
                                    type="checkbox"
                                    name="tools[]"
                                    value={tool.key}
                                    label={tool.name}
                                />
                            ))}
                        </div>
                    </div>
                ))}
            </div>
            <InputError id="demo-tools-error" message={error} />
        </fieldset>
    );
}
