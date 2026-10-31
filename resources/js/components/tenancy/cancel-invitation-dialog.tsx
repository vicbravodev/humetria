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
import { cancel } from '@/routes/companies/invitations';
import type { TenantRow } from '@/types';

/** Asks for confirmation before cancelling the invitation, so its link stops working. */
export default function CancelInvitationDialog({
    tenant,
    invitationId,
}: {
    tenant: TenantRow;
    invitationId: string;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="sm"
                    className="text-muted-foreground"
                    aria-label={`Cancelar invitación de ${tenant.trade_name}`}
                >
                    Cancelar
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>¿Cancelar la invitación?</DialogTitle>
                <DialogDescription>
                    El enlace dejará de funcionar.
                </DialogDescription>
                <Form
                    {...cancel.form({
                        tenant: tenant.id,
                        invitation: invitationId,
                    })}
                    onSuccess={() => setOpen(false)}
                >
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="outline">Volver</Button>
                            </DialogClose>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                            >
                                {processing && <Spinner />}
                                Cancelar invitación
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
