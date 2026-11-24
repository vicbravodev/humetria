import { Head, setLayoutProps } from '@inertiajs/react';
import PageHeader from '@/components/humetria/page-header';
import ReadOnlyNotice from '@/components/humetria/read-only-notice';
import RoleEditorForm from '@/components/tenancy/role-editor-form';
import { Badge } from '@/components/ui/badge';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index } from '@/routes/tenant/roles';
import type {
    PermissionGroup,
    RoleEditorProps,
    RoleTemplateOption,
} from '@/types';

/** A role of the company's own, new (blank or copied from a template) or already made: its name and what it can do. */
export default function RoleEditor(props: {
    role: RoleEditorProps['role'];
    from: string | null;
    templates: RoleTemplateOption[];
    groups: PermissionGroup[];
    can_manage: boolean;
}) {
    const { role, can_manage } = props;
    const tenantSlug = useTenantSlug();
    const title = role?.label ?? 'Nuevo rol';
    setLayoutProps({
        breadcrumbs: [
            { title: 'Roles y permisos', href: index(tenantSlug) },
            { title },
        ],
    });

    return (
        <>
            <Head title={title} />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    title={title}
                    status={role === null ? undefined : <Badge>Propio</Badge>}
                    description={`${role?.based_on == null ? '' : `Basado en ${role.based_on}. `}Elige el nivel de acceso en cada área. «Administrar» incluye «Ver».`}
                />
                {!can_manage && (
                    <ReadOnlyNotice>
                        Estás dentro de la empresa como soporte: puedes revisar
                        este rol, pero solo su dueño lo cambia.
                    </ReadOnlyNotice>
                )}
                <RoleEditorForm {...props} />
            </div>
        </>
    );
}
