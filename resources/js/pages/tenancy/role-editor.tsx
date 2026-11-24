import { Head, setLayoutProps } from '@inertiajs/react';
import Notice from '@/components/humetria/notice';
import PageHeader from '@/components/humetria/page-header';
import ReadOnlyNotice from '@/components/humetria/read-only-notice';
import RoleEditorForm from '@/components/tenancy/role-editor-form';
import { Badge } from '@/components/ui/badge';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { plural } from '@/lib/plural';
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
                    description={
                        role === null || role.based_on === null
                            ? 'Un rol de tu empresa: enciende solo lo que necesita quien lo tenga.'
                            : `Basado en ${role.based_on}.`
                    }
                />
                {!can_manage && (
                    <ReadOnlyNotice>
                        Estás dentro de la empresa como soporte: puedes revisar
                        este rol, pero solo su dueño lo cambia.
                    </ReadOnlyNotice>
                )}
                {role !== null && role.people > 0 && (
                    <Notice>
                        <strong>
                            {plural(
                                role.people,
                                'Una persona tiene',
                                `${role.people} personas tienen`,
                            )}{' '}
                            este rol.
                        </strong>{' '}
                        Lo que cambies aquí cambia lo que pueden hacer en cuanto
                        guardes.
                    </Notice>
                )}
                <RoleEditorForm {...props} />
            </div>
        </>
    );
}
