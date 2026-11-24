import { Link } from '@inertiajs/react';
import { Plus, SearchX, ShieldPlus } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import Pagination from '@/components/humetria/pagination';
import SearchField from '@/components/humetria/search-field';
import RoleList from '@/components/tenancy/role-list';
import { Button } from '@/components/ui/button';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import type { PermissionArea } from '@/lib/permission-areas';
import { create, index } from '@/routes/tenant/roles';
import type { CompanyRoleRow, Paginated } from '@/types';

const toQuery = ({ q }: { q: string }) =>
    q.trim() === '' ? {} : { q: q.trim() };

/**
 * «Roles de tu empresa»: the search by name (on the server) and the page of roles; before the first one, what a role of its
 * own is for and the way to make it.
 */
export default function OwnRoles({
    roles,
    areas,
    search,
    canManage,
}: {
    roles: Paginated<CompanyRoleRow>;
    areas: PermissionArea[];
    search: string;
    canManage: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const { text, type } = useQueryFilters(
        index.url(tenantSlug),
        { q: search },
        toQuery,
    );

    return (
        <div className="flex flex-col gap-3">
            {(roles.total > 0 || search !== '') && (
                <div role="search" className="w-full sm:w-[320px]">
                    <SearchField
                        label="Buscar por nombre"
                        value={text}
                        onChange={type}
                    />
                </div>
            )}
            {roles.data.length > 0 ? (
                <>
                    <RoleList
                        roles={roles.data}
                        areas={areas}
                        canManage={canManage}
                    />
                    <Pagination
                        page={roles}
                        label="Páginas de roles"
                        pinned={false}
                    />
                </>
            ) : search === '' ? (
                <EmptyState
                    icon={ShieldPlus}
                    title="Aún no hay roles propios"
                    action={
                        canManage ? (
                            <Button asChild>
                                <Link href={create(tenantSlug)}>
                                    <Plus aria-hidden />
                                    Nuevo rol
                                </Link>
                            </Button>
                        ) : undefined
                    }
                >
                    Crea uno desde cero o copia una plantilla y ajústala a cómo
                    reparte el trabajo tu empresa.
                </EmptyState>
            ) : (
                <EmptyState icon={SearchX} title="Ningún rol se llama así">
                    Prueba con otra parte del nombre.
                </EmptyState>
            )}
        </div>
    );
}
