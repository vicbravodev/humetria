import { Head, Link, setLayoutProps, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import Notice from '@/components/humetria/notice';
import PageHeader from '@/components/humetria/page-header';
import ReadOnlyNotice from '@/components/humetria/read-only-notice';
import OwnRoles from '@/components/tenancy/own-roles';
import RoleTemplates from '@/components/tenancy/role-templates';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { create } from '@/routes/tenant/roles';
import type { CompanyRoleRow, Paginated, RoleTemplateRow } from '@/types';

/** «Roles y permisos»: the templates of Humetría, locked, then the company's own roles; only its owner changes them. */
export default function Roles({
    templates,
    roles,
    search,
    limit,
    can_manage,
}: {
    templates: RoleTemplateRow[];
    roles: Paginated<CompanyRoleRow>;
    search: string;
    limit: { used: number; max: number };
    can_manage: boolean;
}) {
    // Always set here: this page only exists inside a company.
    const { tenant } = usePage().props;
    const tenantSlug = useTenantSlug();
    const full = limit.used >= limit.max;
    // Its place in the sidebar: the editor's trail must not stay after saving or deleting there (those visits keep the state).
    setLayoutProps({ breadcrumbs: [] });

    return (
        <>
            <Head title="Roles y permisos" />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    title="Roles y permisos"
                    description={`Qué puede hacer cada persona en ${tenant?.trade_name ?? ''}. Usa una plantilla tal cual o crea un rol propio con solo lo que necesita.`}
                >
                    {can_manage && !full && (
                        <Button asChild>
                            <Link href={create(tenantSlug)}>
                                <Plus aria-hidden />
                                Nuevo rol
                            </Link>
                        </Button>
                    )}
                </PageHeader>
                {!can_manage && (
                    <ReadOnlyNotice>
                        Estás dentro de la empresa como soporte: puedes revisar
                        sus roles, pero solo su dueño los cambia.
                    </ReadOnlyNotice>
                )}
                {can_manage && full && (
                    <Notice>
                        <strong>
                            Llegaste al máximo de {limit.max} roles propios.
                        </strong>{' '}
                        Borra uno que no uses para crear otro.
                    </Notice>
                )}
                <RoleTemplates templates={templates} canManage={can_manage} />
                <OwnRoles
                    roles={roles}
                    search={search}
                    limit={limit}
                    canManage={can_manage}
                />
            </div>
        </>
    );
}
