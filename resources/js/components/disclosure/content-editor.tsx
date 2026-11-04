import { useForm, usePage } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { useState } from 'react';
import ContentPublishDialog from '@/components/disclosure/content-publish-dialog';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { destroy, update } from '@/routes/tenant/disclosure/contents/draft';
import type { ContentBlock } from '@/types';

/**
 * The draft of a block of 5.7: its words, to save, publish for the centers chosen or throw away. Plain text: a blank line
 * starts a paragraph. The note says what the norm asks and what to avoid.
 */
export default function ContentEditor({
    block,
    draft,
    centersInUse,
}: {
    block: ContentBlock;
    draft: { id: string; text: string };
    centersInUse: number;
}) {
    const tenantSlug = useTenantSlug();
    const form = useForm({ text: draft.text });
    // The server answers on `sections` (the shape the policy sends too), not on this form's one field.
    const error = usePage().props.errors['sections'];
    const [publishing, setPublishing] = useState(false);
    const route = { tenant: tenantSlug, kind: block.kind, document: draft.id };
    const id = `content-${block.kind}`;

    return (
        <>
            <form
                noValidate
                className="grid gap-3"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.transform((data) => ({
                        sections: [{ key: 'body', text: data.text, items: [] }],
                    }));
                    form.put(update.url(route), { preserveScroll: true });
                }}
            >
                <Label htmlFor={id}>Texto</Label>
                <Textarea
                    id={id}
                    value={form.data.text}
                    rows={8}
                    aria-describedby={`${id}-note`}
                    aria-invalid={error === undefined ? undefined : true}
                    onChange={(event) =>
                        form.setData('text', event.target.value)
                    }
                />
                <p id={`${id}-note`} className="text-sm text-muted-foreground">
                    {block.note}
                </p>
                <InputError message={error} />
                <div className="flex flex-wrap gap-2">
                    <Button
                        type="submit"
                        variant="outline"
                        disabled={form.processing}
                    >
                        {form.processing ? <Spinner /> : <Save aria-hidden />}
                        Guardar borrador
                    </Button>
                    <Button type="button" onClick={() => setPublishing(true)}>
                        Publicar
                    </Button>
                    <ConfirmDialog
                        trigger={
                            <Button type="button" variant="ghost">
                                Descartar
                            </Button>
                        }
                        title="¿Descartar el borrador?"
                        description="Se pierde lo que escribiste. Lo ya publicado no cambia."
                        confirmLabel="Descartar"
                        form={destroy.form(route)}
                    />
                </div>
            </form>
            {/* Outside the form: a submit inside the dialog bubbles, through React, to the form it is drawn from. */}
            {publishing && (
                <ContentPublishDialog
                    open={publishing}
                    onOpenChange={setPublishing}
                    kind={block.kind}
                    documentId={draft.id}
                    label={block.label}
                    centersInUse={centersInUse}
                />
            )}
        </>
    );
}
