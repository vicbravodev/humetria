import { Form } from '@inertiajs/react';
import { Camera, Video } from 'lucide-react';
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
import { store } from '@/routes/tenant/telework/evidence';

/** Asks for photos from the person (they send them from their portal) or schedules a remote check. */
export default function ReviewAskDialog({
    tenantSlug,
    workplaceId,
    kind,
}: {
    tenantSlug: string;
    workplaceId: string;
    kind: 'photos' | 'remote_check';
}) {
    const [open, setOpen] = useState(false);
    const photos = kind === 'photos';

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    {photos ? <Camera aria-hidden /> : <Video aria-hidden />}
                    {photos ? 'Pedir fotos' : 'Pedir comprobación a distancia'}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>
                    {photos
                        ? 'Pedir fotos a la persona'
                        : 'Pedir una comprobación a distancia'}
                </DialogTitle>
                <DialogDescription>
                    {photos
                        ? 'La persona lo verá en su portal y podrá enviar las fotos desde ahí.'
                        : 'Queda anotada como pendiente hasta que registres la comprobación.'}
                </DialogDescription>
                <Form
                    {...store.form({
                        tenant: tenantSlug,
                        workplace: workplaceId,
                    })}
                    noValidate
                    transform={(data) => ({ ...data, kind })}
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor={`ask-${kind}-note`}>
                                    {photos
                                        ? 'Qué debe fotografiar'
                                        : 'Qué se va a comprobar'}
                                </Label>
                                <Textarea id={`ask-${kind}-note`} name="note" />
                                <InputError
                                    message={
                                        errors['note'] ?? errors['workplace']
                                    }
                                />
                            </div>
                            <Button
                                type="submit"
                                disabled={processing}
                                className="w-fit"
                            >
                                {processing && <Spinner />}
                                Pedir
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
