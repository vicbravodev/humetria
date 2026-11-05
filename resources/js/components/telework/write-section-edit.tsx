import { useState } from 'react';
import PreviewSection from '@/components/humetria/preview-section';
import SaveError from '@/components/humetria/save-error';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { useSave } from '@/hooks/use-save';
import type { FlowSection } from '@/lib/question-flow';

/**
 * A section of the document on its review (board V2-Politica: «edita lo que quieras»): its text with the way to rewrite
 * it, and, once rewritten, the way back to the text the answers make.
 */
export default function WriteSectionEdit({
    section,
    url,
}: {
    section: FlowSection;
    url: string;
}) {
    const [editing, setEditing] = useState(false);
    const [text, setText] = useState(section.text ?? '');
    const saving = useSave({ preserveState: true });
    const id = `section-${section.key}`;
    const save = (value: string | null) =>
        saving.save(url, { section: section.key, text: value }, () =>
            setEditing(false),
        );

    if (!editing) {
        return (
            <PreviewSection section={section} current={false}>
                <div className="flex flex-wrap gap-x-5 pt-1 print:hidden">
                    <button
                        type="button"
                        className="touch-hit text-sm font-medium text-primary"
                        onClick={() => {
                            setText(section.text ?? '');
                            setEditing(true);
                        }}
                    >
                        Editar
                        <span className="sr-only"> {section.heading}</span>
                    </button>
                    {section.edited && (
                        <button
                            type="button"
                            className="touch-hit text-sm font-medium text-primary"
                            onClick={() => save(null)}
                        >
                            Volver al texto de tus respuestas
                        </button>
                    )}
                </div>
            </PreviewSection>
        );
    }

    return (
        <div className="-mx-3 flex flex-col gap-2 rounded-lg bg-accent px-3 py-2.5">
            <label htmlFor={id} className="font-semibold">
                {section.heading}
            </label>
            <Textarea
                id={id}
                value={text}
                rows={6}
                onChange={(event) => setText(event.target.value)}
                aria-invalid={saving.errors['text'] !== undefined}
            />
            {saving.errors['text'] !== undefined && (
                <p role="alert" className="text-sm text-destructive">
                    {saving.errors['text']}
                </p>
            )}
            <div className="flex flex-wrap gap-2">
                <Button onClick={() => save(text)} disabled={saving.processing}>
                    Guardar
                </Button>
                <Button variant="outline" onClick={() => setEditing(false)}>
                    Cancelar
                </Button>
            </div>
            {saving.failed && (
                <SaveError
                    onRetry={saving.retry}
                    processing={saving.processing}
                />
            )}
        </div>
    );
}
