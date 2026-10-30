import { Form } from '@inertiajs/react';
import { useState } from 'react';
import TenantField from '@/components/tenancy/tenant-field';
import MemberRoleField from '@/components/tenancy/member-role-field';
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
import { store } from '@/routes/tenant/members/invitations';
import type { RoleOption } from '@/types';

export default function InviteMemberDialog({
    tenantSlug,
    roles,
}: {
    tenantSlug: string;
    roles: RoleOption[];
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>Invitar</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Invitar a la empresa</DialogTitle>
                <DialogDescription>
                    Le llegará un correo con un enlace de un solo uso, válido 7
                    días.
                </DialogDescription>
                <Form
                    {...store.form(tenantSlug)}
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
                                    error={errors['name']}
                                />
                                <TenantField
                                    name="email"
                                    label="Correo electrónico"
                                    type="email"
                                    required
                                    maxLength={255}
                                    error={errors['email']}
                                />
                                <MemberRoleField
                                    roles={roles}
                                    defaultValue="hr"
                                    error={errors['role']}
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
