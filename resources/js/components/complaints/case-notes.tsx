import { Form } from '@inertiajs/react';
import { EyeOff, Plus } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/humetria/input-error';
import Pagination from '@/components/humetria/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { shortDay } from '@/lib/complaints/deadline';
import { store } from '@/routes/tenant/complaints/cases/notes';
import type { CaseEntryRow, Paginated } from '@/types';

/** «Notas internas» (board V4-Quejas-Caso): what the handlers write for themselves, the newest first. The person never reads it. */
export default function CaseNotes({
    caseId,
    notes,
    writable,
}: {
    caseId: string;
    notes: Paginated<CaseEntryRow>;
    writable: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const [writing, setWriting] = useState(false);

    return (
        <section
            aria-labelledby="case-notes"
            className="flex flex-col gap-4 card p-6"
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 id="case-notes" className="text-lg font-semibold">
                    Notas internas
                </h2>
                <Badge variant="outline" className="before:hidden">
                    <EyeOff aria-hidden />
                    La persona no las ve
                </Badge>
            </div>
            {notes.data.map((note) => (
                <div
                    key={note.id}
                    className="flex flex-col gap-1 rounded-lg bg-muted px-4 py-3 text-sm"
                >
                    <span className="text-muted-foreground">
                        {note.author ?? 'Quien atiende'}
                        {note.when !== null && ` · ${shortDay(note.when)}`}
                        {note.time !== null && ` ${note.time}`}
                    </span>
                    <span className="whitespace-pre-line">{note.text}</span>
                </div>
            ))}
            <Pagination
                page={notes}
                label="Páginas de las notas"
                previous="Más recientes"
                next="Anteriores"
                pinned={false}
            />
            {writable && !writing && (
                <Button
                    variant="outline"
                    className="self-start"
                    onClick={() => setWriting(true)}
                >
                    <Plus aria-hidden />
                    Agregar nota
                </Button>
            )}
            {writable && writing && (
                <Form
                    {...store.form({ tenant: tenantSlug, complaint: caseId })}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setWriting(false)}
                    className="flex flex-col gap-2"
                >
                    {({ processing, errors }) => (
                        <>
                            <Label htmlFor="case-note">Nota interna</Label>
                            <Textarea
                                id="case-note"
                                name="body"
                                rows={3}
                                aria-invalid={
                                    errors['body'] === undefined
                                        ? undefined
                                        : true
                                }
                            />
                            <InputError message={errors['body']} />
                            <div className="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => setWriting(false)}
                                >
                                    Cancelar
                                </Button>
                                <Button variant="outline" disabled={processing}>
                                    {processing && <Spinner />}
                                    Guardar nota
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            )}
        </section>
    );
}
