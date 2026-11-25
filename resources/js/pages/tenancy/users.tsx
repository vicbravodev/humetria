import { Head } from '@inertiajs/react';
import PageHeader from '@/components/humetria/page-header';
import PermissionsTable from '@/components/tenancy/permissions-table';
import UsersList from '@/components/tenancy/users-list';
import UsersTabs from '@/components/tenancy/users-tabs';
import type {
    Paginated,
    PlatformPermissionRow,
    PlatformUserCounts,
    PlatformUserFilters,
    PlatformUserRow,
} from '@/types';

export default function PlatformUsers({
    users,
    permissions,
    counts,
    filters,
}: {
    users: Paginated<PlatformUserRow> | null;
    permissions: PlatformPermissionRow[] | null;
    counts: PlatformUserCounts;
    filters: PlatformUserFilters;
}) {
    return (
        <>
            <Head title="Equipo Humetría" />
            <div className="flex flex-col gap-6 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    title="Equipo Humetría"
                    description="Quién del equipo entra a la consola y qué puede hacer, y ayuda para cualquier persona con cuenta que no logra entrar. El rol de soporte se da y se quita aquí; crear un superadmin sigue siendo por terminal."
                />
                <UsersTabs filters={filters} counts={counts} />
                {users !== null && (
                    <UsersList users={users} filters={filters} />
                )}
                {permissions !== null && (
                    <PermissionsTable permissions={permissions} />
                )}
            </div>
        </>
    );
}
