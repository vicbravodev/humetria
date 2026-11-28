import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/humetria/input-error';
import Pagination from '@/components/humetria/pagination';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { store } from '@/routes/companies/notes';
import type { Paginated, TenantNoteRow } from '@/types';

/** What the staff writes down about the company, newest first, and the field to add one. Only the staff sees them. */
export default function CompanyNotes({
    companyId,
    notes,
}: {
    companyId: string;
    notes: Paginated<TenantNoteRow>;
}) {
    const [writing, setWriting] = useState(false);

    return (
        <section aria-labelledby="company-notes" className="card">
            <div className="flex items-center gap-3 border-b px-6 py-4">
                <h2
                    id="company-notes"
                    className="flex-1 text-base font-semibold"
                >
                    Notas internas
                </h2>
                {!writing && (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setWriting(true)}
                    >
                        Agregar
                    </Button>
                )}
            </div>
            <Form
                {...store.form(companyId)}
                options={{ preserveScroll: true }}
                resetOnSuccess
                hidden={!writing}
                className="grid gap-2 border-b px-6 py-4"
            >
                {({ processing, errors }) => (
                    <>
                        <Label htmlFor="note-body">Nueva nota</Label>
                        <Textarea
                            id="note-body"
                            name="body"
                            rows={2}
                            maxLength={2000}
                            placeholder="Solo la ve el equipo de Humetría"
                            aria-invalid={errors['body'] !== undefined}
                            aria-describedby="note-body-error"
                        />
                        <InputError
                            id="note-body-error"
                            message={errors['body']}
                        />
                        <Button
                            type="submit"
                            size="sm"
                            variant="outline"
                            disabled={processing}
                            className="justify-self-start"
                        >
                            {processing && <Spinner />}
                            Guardar nota
                        </Button>
                    </>
                )}
            </Form>
            {notes.data.length === 0 ? (
                <p className="px-6 py-4 text-sm text-muted-foreground">
                    Aún no hay notas.
                </p>
            ) : (
                <ul className="divide-y">
                    {notes.data.map((note) => (
                        <li key={note.id} className="grid gap-1 px-6 py-3.5">
                            <p className="font-medium whitespace-pre-line">
                                {note.body}
                            </p>
                            <p className="text-caption text-muted-foreground">
                                {note.author ?? 'Alguien que ya no está'} ·{' '}
                                {note.at}
                            </p>
                        </li>
                    ))}
                </ul>
            )}
            <Pagination page={notes} label="Páginas de notas" pinned={false} />
        </section>
    );
}
