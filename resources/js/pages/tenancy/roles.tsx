import { Head, Link, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import CountTabs from '@/components/humetria/count-tabs';
import Notice from '@/components/humetria/notice';
import PageHeader from '@/components/humetria/page-header';
import ReadOnlyNotice from '@/components/humetria/read-only-notice';
import OwnRoles from '@/components/tenancy/own-roles';
import RoleTemplates from '@/components/tenancy/role-templates';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { openAreas } from '@/lib/permission-areas';
import { rolesTab } from '@/lib/roles';
import { create, index } from '@/routes/tenant/roles';
import type {
    CompanyRoleRow,
    Paginated,
    PermissionGroup,
    RoleTemplateRow,
} from '@/types';

/**
 * «Roles y permisos» (board Roles 7c): the company's own roles and the templates of Humetría in two tabs, each role with what
 * it lets do in one sentence; only its owner changes them.
 */
export default function Roles({
    templates,
    roles,
    search,
    groups,
    limit,
    can_manage,
}: {
    templates: RoleTemplateRow[];
    roles: Paginated<CompanyRoleRow>;
    search: string;
    groups: PermissionGroup[];
    limit: { used: number; max: number };
    can_manage: boolean;
}) {
    const { url } = usePage();
    const tenantSlug = useTenantSlug();
    const full = limit.used >= limit.max;
    const tab = rolesTab(url, limit.used);
    const areas = openAreas(groups);

    return (
        <>
            <Head title="Roles y permisos" />
            <div className="flex flex-col gap-6 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    title="Roles y permisos"
                    description="Decide qué puede hacer cada persona. Empieza con una plantilla y quita o agrega lo que haga falta."
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
                <CountTabs
                    label="Roles"
                    tabs={[
                        {
                            label: 'Roles de tu empresa',
                            count: limit.used,
                            href: index(tenantSlug, {
                                query: { tab: 'propios' },
                            }),
                            active: tab === 'propios',
                        },
                        {
                            label: 'Plantillas de Humetría',
                            count: templates.length,
                            href: index(tenantSlug, {
                                query: { tab: 'plantillas' },
                            }),
                            active: tab === 'plantillas',
                        },
                    ]}
                />
                {tab === 'propios' ? (
                    <OwnRoles
                        roles={roles}
                        areas={areas}
                        search={search}
                        canManage={can_manage}
                    />
                ) : (
                    <RoleTemplates
                        templates={templates}
                        areas={areas}
                        canCopy={can_manage && !full}
                    />
                )}
                <p className="text-sm text-pretty text-muted-foreground tabular-nums">
                    Tienes {limit.used} de {limit.max} roles propios. Las
                    plantillas de Humetría no se editan: cópialas para crear un
                    rol propio a partir de ellas.
                </p>
            </div>
        </>
    );
}
