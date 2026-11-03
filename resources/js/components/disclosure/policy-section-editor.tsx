import { Trash2 } from 'lucide-react';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { joinLines, splitLines } from '@/lib/disclosure';
import type { PolicySectionData } from '@/types';

/**
 * One section of the policy, to write: its words and, when it lists, one item per line. A section the guide lets go has a
 * button to take it out; the ones it requires (compromisos, responsables, recursos) stay (criterio C-51).
 */
export default function PolicySectionEditor({
    section,
    help,
    error,
    onChange,
    onRemove,
}: {
    section: PolicySectionData;
    /** What to write here, under the box. */
    help: string | null;
    error: string | undefined;
    onChange: (change: { text?: string; items?: string[] }) => void;
    onRemove: (() => void) | null;
}) {
    const id = `section-${section.key}`;
    const lists = section.listStyle !== null;

    return (
        <section
            aria-labelledby={`${id}-heading`}
            className="grid gap-4 card p-5"
        >
            <div className="flex items-start justify-between gap-4">
                <h3 id={`${id}-heading`} className="text-lg font-semibold">
                    {section.heading}
                    {section.required && (
                        <span className="ml-2 text-sm font-normal text-muted-foreground">
                            Obligatorio
                        </span>
                    )}
                </h3>
                {onRemove !== null && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={onRemove}
                    >
                        <Trash2 aria-hidden />
                        Quitar
                        <span className="sr-only"> {section.heading}</span>
                    </Button>
                )}
            </div>
            <div className="grid gap-2">
                <Label htmlFor={`${id}-text`}>Texto</Label>
                <Textarea
                    id={`${id}-text`}
                    value={section.text}
                    rows={section.text.length > 200 ? 5 : 3}
                    aria-invalid={error !== undefined}
                    aria-describedby={help === null ? undefined : `${id}-help`}
                    onChange={(event) => onChange({ text: event.target.value })}
                />
                {help !== null && (
                    <p
                        id={`${id}-help`}
                        className="text-sm text-muted-foreground"
                    >
                        {help}
                    </p>
                )}
            </div>
            {lists && (
                <div className="grid gap-2">
                    <Label htmlFor={`${id}-items`}>
                        Elementos, uno por renglón
                    </Label>
                    <Textarea
                        id={`${id}-items`}
                        value={joinLines(section.items)}
                        rows={Math.min(section.items.length + 1, 12)}
                        onChange={(event) =>
                            onChange({ items: splitLines(event.target.value) })
                        }
                    />
                </div>
            )}
            <InputError message={error} />
        </section>
    );
}
