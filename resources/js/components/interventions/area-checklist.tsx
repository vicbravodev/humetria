import InputError from '@/components/humetria/input-error';
import { areaChoices } from '@/lib/action-form';
import type { AreaOption } from '@/types';

/**
 * The areas an action can be for: only those Results shows (the minimum of questionnaires per group applies), each with how
 * many of its questionnaires are in Medio or more for this row, which is what Resultados shows when filtered by that area.
 */
export default function AreaChecklist({
    areas,
    itemId,
    selected,
    error,
}: {
    areas: AreaOption[];
    itemId: string;
    selected: string[];
    error: string | undefined;
}) {
    const choices = areaChoices(areas, itemId);

    if (choices.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Ninguna área se muestra todavía: con pocos cuestionarios por
                área, Resultados no las separa. Elige «Todo el centro».
            </p>
        );
    }

    return (
        <fieldset className="grid gap-2">
            <legend className="mb-1 text-sm font-medium">Áreas</legend>
            {choices.map((area) => (
                <label
                    key={area.id}
                    className="flex min-h-11 cursor-pointer items-start gap-3 rounded-lg border border-input bg-card px-4 py-2.5 hover:bg-muted has-checked:border-primary has-checked:bg-accent has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring"
                >
                    <input
                        type="checkbox"
                        name="area_ids[]"
                        value={area.id}
                        defaultChecked={selected.includes(area.id)}
                        className="mt-0.5 size-5 shrink-0 accent-primary"
                    />
                    <span className="grid">
                        {area.name}
                        <span className="text-sm text-muted-foreground">
                            {area.hint}
                        </span>
                    </span>
                </label>
            ))}
            <InputError message={error} />
        </fieldset>
    );
}
