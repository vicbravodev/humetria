import { Form } from '@inertiajs/react';
import TenantField from '@/components/tenancy/tenant-field';
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
import { ownerAction } from '@/lib/tenant-status';
import { store } from '@/routes/companies/invitations';
import type { TenantRow } from '@/types';

/** Invites the person who will own the company, or replaces the owner it has. */
export default function InviteOwnerDialog({
    tenant,
    open,
    onOpenChange,
}: {
    tenant: TenantRow;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const label = ownerAction(tenant.owner);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogTitle>{label}</DialogTitle>
                <DialogDescription>
                    Le enviamos un correo con un enlace, válido por 7 días, para
                    que entre a {tenant.trade_name}.
                </DialogDescription>
                <Form
                    {...store.form(tenant.id)}
                    noValidate
                    onSuccess={() => onOpenChange(false)}
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-4">
                                <TenantField
                                    name="name"
                                    label="Nombre"
                                    required
                                    maxLength={255}
                                    autoComplete="off"
                                    error={errors['name']}
                                />
                                <TenantField
                                    type="email"
                                    name="email"
                                    label="Correo electrónico"
                                    required
                                    maxLength={255}
                                    autoComplete="off"
                                    error={errors['email']}
                                />
                            </div>
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="outline">Cancelar</Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Enviar invitación
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
