import { Form } from '@inertiajs/react';
import { UserMinus } from 'lucide-react';
import { useState } from 'react';
import EndTeleworkFields from '@/components/telework/end-telework-fields';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { end } from '@/routes/tenant/telework/teleworkers';
import type { TeleworkerSummary } from '@/types';

/** Ends a period with a reason, the day and an optional note. Nothing is deleted: it stays in the history. */
export default function EndTeleworkDialog({
    tenantSlug,
    teleworker,
}: {
    tenantSlug: string;
    teleworker: TeleworkerSummary;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <UserMinus aria-hidden />
                    Terminar teletrabajo
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>
                    ¿Terminar el teletrabajo de {teleworker.name}?
                </DialogTitle>
                <DialogDescription>
                    Queda en el historial del padrón con su fecha y motivo; no
                    se borra nada.
                </DialogDescription>
                <Form
                    {...end.form({
                        tenant: tenantSlug,
                        teleworker: teleworker.id,
                    })}
                    noValidate
                    onSuccess={() => setOpen(false)}
                    className="grid gap-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <EndTeleworkFields
                                teleworker={teleworker}
                                errors={errors}
                            />
                            <InputError
                                role="alert"
                                message={errors['teleworker']}
                            />
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                                className="w-fit"
                            >
                                {processing && <Spinner />}
                                Confirmar y terminar
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
