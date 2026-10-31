import { Form } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { destroy } from '@/routes/tenant/campaigns/shares';
import type { ReportShareRow } from '@/types';

/** Revokes one link to the report, once confirmed: whoever has it stops opening the report at once. */
export default function RevokeShareDialog({
    route,
    share,
}: {
    /** The company's slug and the campaign's id. */
    route: [string, string];
    share: ReportShareRow;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="outline"
                    size="sm"
                    aria-label={`Revocar el enlace que vence el ${share.expiresAt}`}
                >
                    Revocar
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>¿Revocar este enlace?</DialogTitle>
                <DialogDescription>
                    Quien lo tenga deja de abrir el informe en ese momento. Si
                    lo necesita otra vez, crea un enlace nuevo.
                </DialogDescription>
                <Form
                    {...destroy.form([...route, share.id])}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                >
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="outline">Cancelar</Button>
                            </DialogClose>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                            >
                                {processing && <Spinner />}
                                Revocar enlace
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
