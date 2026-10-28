import { Form } from '@inertiajs/react';
import { useState } from 'react';
import TenantField from '@/components/tenancy/tenant-field';
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
import { store } from '@/routes/companies/invitations';
import type { TenantRow } from '@/types';

/** Invites the person who will own the company, or replaces the owner it has. */
export default function InviteOwnerDialog({ tenant }: { tenant: TenantRow }) {
    const [open, setOpen] = useState(false);
    const label = tenant.owner === null ? 'Invitar owner' : 'Cambiar owner';

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="outline"
                    aria-label={`${label} de ${tenant.trade_name}`}
                >
                    {label}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>{label}</DialogTitle>
                <DialogDescription>
                    Le enviamos un correo con un enlace, válido por 7 días, para
                    que entre a {tenant.trade_name}.
                </DialogDescription>
                <Form
                    {...store.form(tenant.id)}
                    noValidate
                    onSuccess={() => setOpen(false)}
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
