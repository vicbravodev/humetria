import StoryCategoryField from '@/components/complaints-public/story-category-field';
import DatePicker from '@/components/humetria/date-picker';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { Draft } from '@/lib/complaints/form';
import { NOTICE } from '@/lib/complaints/form';
import type { ComplaintCategoryOption } from '@/types';

/** Step 2: what happened, when (about) and, if known, what kind of thing it is. */
export default function StoryStep({
    draft,
    categories,
    limits,
    today,
    onChange,
}: {
    draft: Draft;
    categories: ComplaintCategoryOption[];
    limits: { min: number; max: number };
    today: string;
    onChange: (changes: Partial<Draft>) => void;
}) {
    return (
        <div className="flex flex-col gap-5">
            <h2 className="text-xl font-semibold">¿Qué pasó?</h2>
            <div className="grid gap-2">
                <Label htmlFor="complaint-description">
                    Cuéntelo con sus palabras
                </Label>
                <Textarea
                    id="complaint-description"
                    rows={8}
                    value={draft.description}
                    onChange={(event) =>
                        onChange({ description: event.target.value })
                    }
                    aria-describedby="complaint-description-count"
                />
                <p
                    id="complaint-description-count"
                    className="text-caption text-muted-foreground"
                >
                    {draft.description.trim().length} de {limits.max}{' '}
                    caracteres. Escriba lo que ocurrió, no lo que cree que debe
                    decir.
                </p>
            </div>
            <div className="grid gap-2">
                <Label htmlFor="complaint-date">
                    {draft.type === NOTICE
                        ? '¿Cuándo ocurrió?'
                        : '¿Cuándo pasó, más o menos? (opcional)'}
                </Label>
                <DatePicker
                    id="complaint-date"
                    name="occurred_on"
                    value={draft.occurredOn}
                    onChange={(occurredOn) => onChange({ occurredOn })}
                    max={today}
                />
            </div>
            <StoryCategoryField
                categories={categories}
                value={draft.category}
                onChange={(category) => onChange({ category })}
            />
        </div>
    );
}
