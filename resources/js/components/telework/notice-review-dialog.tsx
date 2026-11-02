import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { review } from '@/routes/tenant/telework/notices';

/** Reviews a notice with a note. It is done once: the note stays as evidence. */
export default function NoticeReviewDialog({
    tenantSlug,
    noticeId,
    person,
}: {
    tenantSlug: string;
    noticeId: string;
    person: string;
}) {
    const [open, setOpen] = useState(false);
    const id = `review-note-${noticeId}`;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">Revisar</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Revisar el aviso de {person}</DialogTitle>
                <DialogDescription>
                    Anota qué se hará o por qué no hace falta actuar. La
                    revisión se hace una vez y queda como constancia.
                </DialogDescription>
                <Form
                    {...review.form({ tenant: tenantSlug, notice: noticeId })}
                    noValidate
                    onSuccess={() => setOpen(false)}
                    className="grid gap-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor={id}>Nota de la revisión</Label>
                                <Textarea
                                    id={id}
                                    name="note"
                                    maxLength={2000}
                                    aria-invalid={
                                        errors['note'] === undefined
                                            ? undefined
                                            : true
                                    }
                                    aria-describedby={`${id}-error`}
                                />
                                <InputError
                                    id={`${id}-error`}
                                    message={errors['note'] ?? errors['notice']}
                                />
                            </div>
                            <Button
                                type="submit"
                                disabled={processing}
                                className="w-fit"
                            >
                                {processing && <Spinner />}
                                Registrar revisión
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
