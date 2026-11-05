import { Form, Link } from '@inertiajs/react';
import { Send } from 'lucide-react';
import MemberRoleChoice from '@/components/tenancy/member-role-choice';
import TenantField from '@/components/tenancy/tenant-field';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index } from '@/routes/tenant/members';
import { store } from '@/routes/tenant/members/invitations';
import type { RoleOption } from '@/types';

/**
 * Who is invited and what they will do (board V3-Roles). The link goes by email, single use, for 7 days; the people of a
 * center never come in here: they get their personal link from the route.
 */
export default function InviteMemberForm({ roles }: { roles: RoleOption[] }) {
    const tenantSlug = useTenantSlug();

    return (
        <Form
            {...store.form(tenantSlug)}
            noValidate
            className="flex flex-col gap-6"
        >
            {({ processing, errors }) => (
                <>
                    <TenantField
                        name="name"
                        label="Nombre"
                        required
                        maxLength={255}
                        error={errors['name']}
                    />
                    <TenantField
                        name="email"
                        label="Correo"
                        type="email"
                        required
                        maxLength={255}
                        error={errors['email']}
                    />
                    <MemberRoleChoice roles={roles} error={errors['role']} />
                    <p className="text-sm text-pretty text-muted-foreground">
                        Le llegará un correo con un enlace de un solo uso,
                        válido por 7 días. Las personas trabajadoras no se
                        invitan aquí: reciben su enlace personal desde la ruta.
                        A quien atiende quejas se le invita primero aquí y luego
                        se le designa desde el canal de quejas.
                    </p>
                    <div className="flex flex-wrap gap-2.5">
                        <Button type="submit" disabled={processing}>
                            {processing ? <Spinner /> : <Send aria-hidden />}
                            Enviar invitación
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={index(tenantSlug)}>Cancelar</Link>
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
