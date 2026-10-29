import { Form } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { deactivate } from '@/routes/companies';
import type { TenantRow } from '@/types';

/** Asks for confirmation before blocking a company. Its data is kept. */
export default function DeactivateTenantDialog({
    tenant,
    open,
    onOpenChange,
}: {
    tenant: TenantRow;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const hasTrialLeft =
        tenant.status === 'trial' || tenant.status === 'not_started';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogTitle>¿Desactivar {tenant.trade_name}?</DialogTitle>
                <DialogDescription>
                    Nadie de esta empresa podrá usar Humetría hasta que la
                    actives con una fecha nueva. Sus datos se conservan.
                    {hasTrialLeft && ' La prueba no se puede reanudar.'}
                </DialogDescription>
                <Form
                    {...deactivate.form(tenant.id)}
                    onSuccess={() => onOpenChange(false)}
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
