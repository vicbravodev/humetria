import { Form } from '@inertiajs/react';
import { useState } from 'react';
import PortalNoticeFields from '@/components/telework/portal/portal-notice-fields';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/portal/notices';
import type { NoticeKindValue } from '@/types';

/** The form of one written notice; the person is whoever owns the link, so the name is shown and not typed. */
export default function PortalNoticeDialog({
    token,
    kind,
    title,
    name,
    today,
    states,
}: {
    token: string;
    kind: NoticeKindValue;
    title: string;
    name: string;
    today: string;
    states: Record<string, string>;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" className="h-12 text-base">
                    Escribir este aviso
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90dvh] overflow-y-auto">
                <DialogTitle>{title}</DialogTitle>
                <DialogDescription>
                    Aviso de {name}. Queda constancia de la fecha, la hora y la
                    dirección IP desde la que lo envía.
                </DialogDescription>
                <Form
                    {...store.form({ token })}
                    noValidate
                    onSuccess={() => setOpen(false)}
                    className="grid gap-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <input type="hidden" name="kind" value={kind} />
                            <PortalNoticeFields
                                kind={kind}
                                today={today}
                                states={states}
                                errors={errors}
                            />
                            <Button
                                type="submit"
                                disabled={processing}
                                className="h-12 w-fit text-base"
                            >
                                {processing && <Spinner />}
                                Enviar aviso
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
