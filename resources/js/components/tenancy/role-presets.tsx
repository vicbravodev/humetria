import { TEMPLATE_HINTS } from '@/lib/permission-areas';
import { cn } from '@/lib/utils';
import type { RoleTemplateOption } from '@/types';

const BLANK = 'blank';

/**
 * «Empezar desde» of a role (board Roles 7c): «Desde cero» or a template of Humetría, as cards that are native radios.
 * Choosing one sets every area to what the template has; after that each area is adjusted on its own. On a role already
 * made (`editing`) nothing is chosen at first, and choosing says that nothing is saved yet.
 */
export default function RolePresets({
    templates,
    value,
    editing,
    onChoose,
}: {
    templates: RoleTemplateOption[];
    /** The template chosen, null for «Desde cero», undefined while none is (a role being edited). */
    value: string | null | undefined;
    editing: boolean;
    onChoose: (template: RoleTemplateOption | null) => void;
}) {
    const options = [
        {
            key: BLANK,
            label: 'Desde cero',
            hint: 'Sin ningún permiso',
            template: null,
        },
        ...templates.map((template) => ({
            key: template.key,
            label: template.label,
            hint: TEMPLATE_HINTS[template.key] ?? '',
            template,
        })),
    ];

    return (
        <fieldset className="flex min-w-0 flex-col gap-2">
            <legend className="mb-2 font-medium">Empezar desde</legend>
            <div className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:grid sm:grid-cols-[repeat(auto-fill,minmax(10rem,1fr))] sm:overflow-visible sm:px-0">
                {options.map((option) => (
                    <label
                        key={option.key}
                        data-test={`preset-${option.key}`}
                        className={cn(
                            'flex min-h-11 w-44 shrink-0 cursor-pointer flex-col gap-0.5 rounded-lg border border-input bg-card px-3 py-2.5 transition-colors duration-100 ease-out hover:bg-muted sm:w-auto',
                            'has-checked:border-2 has-checked:border-primary has-checked:bg-accent has-checked:px-[11px] has-checked:py-[9px]',
                            'has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring',
                        )}
                    >
                        <input
                            type="radio"
                            name="preset"
                            value={option.key}
                            checked={
                                value !== undefined &&
                                (value ?? BLANK) === option.key
                            }
                            onChange={() => onChoose(option.template)}
                            className="sr-only"
                        />
                        <span className="text-sm leading-5 font-semibold">
                            {option.label}
                        </span>
                        <span className="text-caption text-muted-foreground">
                            {option.hint}
                        </span>
                    </label>
                ))}
            </div>
            {editing && value !== undefined && (
                <p className="text-sm text-pretty text-muted-foreground">
                    Todavía no se guarda nada: elige «Guardar rol» para aplicar
                    estos niveles.
                </p>
            )}
        </fieldset>
    );
}
