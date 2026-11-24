import { Head, setLayoutProps } from '@inertiajs/react';
import PageHeader from '@/components/humetria/page-header';
import ReadOnlyNotice from '@/components/humetria/read-only-notice';
import MemberPermissionsForm from '@/components/tenancy/member-permissions-form';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index } from '@/routes/tenant/members';
import type { PermissionGroup } from '@/types';

/**
 * «Permisos adicionales» of one person (spec: Miembros): what they have besides their role, and where each permission comes
 * from. Only adds: what the role brings stays on, locked; to have less, they need another role.
 */
export default function MemberPermissions({
    member,
    groups,
    can_manage,
}: {
    member: { id: number; name: string; role_label: string | null };
    groups: PermissionGroup[];
    can_manage: boolean;
}) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: [
            { title: 'Usuarios', href: index(tenantSlug) },
            { title: 'Permisos adicionales' },
        ],
    });

    return (
        <>
            <Head title="Permisos adicionales" />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    title={`Permisos adicionales de ${member.name}`}
                    description={
                        <>
                            {member.role_label !== null && (
                                <strong className="font-medium text-foreground">
                                    Su rol: {member.role_label}.{' '}
                                </strong>
                            )}
                            Aquí le sumas permisos a los de su rol. Lo que trae
                            su rol no se quita aquí: para que tenga menos,
                            cámbiale el rol.
                        </>
                    }
                />
                {!can_manage && (
                    <ReadOnlyNotice>
                        Estás dentro de la empresa como soporte: puedes revisar
                        estos permisos, pero solo su dueño los cambia.
                    </ReadOnlyNotice>
                )}
                <MemberPermissionsForm
                    memberId={member.id}
                    groups={groups}
                    canManage={can_manage}
                />
            </div>
        </>
    );
}
