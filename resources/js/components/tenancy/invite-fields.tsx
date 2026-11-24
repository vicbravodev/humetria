import { Link } from '@inertiajs/react';
import { Send } from 'lucide-react';
import InviteExtraPermissions from '@/components/tenancy/invite-extra-permissions';
import MemberRoleChoice from '@/components/tenancy/member-role-choice';
import TenantField from '@/components/tenancy/tenant-field';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index } from '@/routes/tenant/members';
import type { InvitableRole, PermissionGroup } from '@/types';

/** Who is invited, what they will do, the extras past the role and the send button: the form's left column. */
export default function InviteFields({
    roles,
    groups,
    state,
}: {
    roles: InvitableRole[];
    groups: PermissionGroup[];
    state: {
        role: { value: string; onChange: (value: string) => void };
        chosen: { value: string[]; onChange: (value: string[]) => void };
        processing: boolean;
        errors: Partial<Record<string, string>>;
    };
}) {
    const tenantSlug = useTenantSlug();
    const { errors } = state;

    return (
        <div className="flex min-w-0 flex-col gap-6">
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
            <MemberRoleChoice
                roles={roles}
                choice={state.role}
                error={errors['role']}
            />
            <InviteExtraPermissions
                groups={groups}
                base={
                    roles.find((option) => option.value === state.role.value)
                        ?.permissions ?? []
                }
                chosen={state.chosen}
                errors={{
                    permissions: errors['permissions'],
                    acknowledge: errors['acknowledge_sensitive'],
                }}
            />
            <p className="text-sm text-pretty text-muted-foreground">
                Le llegará un correo con un enlace de un solo uso, válido por 7
                días. Las personas trabajadoras no se invitan aquí: reciben su
                enlace personal desde la ruta. A quien atiende quejas se le
                invita primero aquí y luego se le designa desde el canal de
                quejas.
            </p>
            <div className="flex flex-wrap gap-2.5">
                <Button type="submit" disabled={state.processing}>
                    {state.processing ? <Spinner /> : <Send aria-hidden />}
                    Enviar invitación
                </Button>
                <Button variant="outline" asChild>
                    <Link href={index(tenantSlug)}>Cancelar</Link>
                </Button>
            </div>
        </div>
    );
}
