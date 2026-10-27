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
import { deactivate } from '@/routes/companies';
import type { TenantRow } from '@/types';

/** Asks for confirmation before blocking a company. Its data is kept. */
export default function DeactivateTenantDialog({
    tenant,
}: {
    tenant: TenantRow;
}) {
    const [open, setOpen] = useState(false);
    const hasTrialLeft =
        tenant.status === 'trial' || tenant.status === 'not_started';

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="outline"
                    aria-label={`Desactivar ${tenant.trade_name}`}
                >
                    Desactivar
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>¿Desactivar {tenant.trade_name}?</DialogTitle>
                <DialogDescription>
                    Nadie de esta empresa podrá usar Humetría hasta que la
                    actives con una fecha nueva. Sus datos se conservan.
                    {hasTrialLeft && ' La prueba no se puede reanudar.'}
                </DialogDescription>
                <Form
                    {...deactivate.form(tenant.id)}
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
                                Desactivar
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
