import { Form } from '@inertiajs/react';
import { Send, UserPlus } from 'lucide-react';
import { useState } from 'react';
import MemberRoleChoice from '@/components/tenancy/member-role-choice';
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
import { store } from '@/routes/tenant/members/invitations';
import type { RoleOption } from '@/types';

export default function InviteMemberDialog({
    tenantSlug,
    tradeName,
    roles,
}: {
    tenantSlug: string;
    tradeName: string;
    roles: RoleOption[];
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <UserPlus aria-hidden />
                    Invitar
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-[540px]">
                <DialogTitle>Invitar a {tradeName}</DialogTitle>
                <DialogDescription>
                    Le llegará un correo con un enlace de un solo uso, válido
                    por 7 días.
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
                                <MemberRoleChoice
                                    roles={roles}
                                    error={errors['role']}
                                />
                            </div>
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="outline">Cancelar</Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing ? (
                                        <Spinner />
                                    ) : (
                                        <Send aria-hidden />
                                    )}
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
