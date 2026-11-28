import { Head } from '@inertiajs/react';
import PageHeader from '@/components/humetria/page-header';
import PermissionsTable from '@/components/tenancy/permissions-table';
import UsersList from '@/components/tenancy/users-list';
import UsersTabs from '@/components/tenancy/users-tabs';
import UsersTeamArea from '@/components/tenancy/users-team-area';
import type {
    Paginated,
    PlatformPermissionRow,
    PlatformUserCounts,
    PlatformUserFilters,
    PlatformUserRow,
} from '@/types';

export default function PlatformUsers({
    users,
    people,
    permissions,
    counts,
    filters,
}: {
    users: Paginated<PlatformUserRow> | null;
    people: Paginated<PlatformUserRow> | null;
    permissions: PlatformPermissionRow[] | null;
    counts: PlatformUserCounts;
    filters: PlatformUserFilters;
}) {
    return (
        <>
            <Head title="Equipo Humetría" />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    title="Equipo Humetría"
                    description="Quién del equipo puede entrar a la consola y qué puede hacer. Hoy el rol de soporte se da y se quita aquí; crear un superadmin sigue siendo por terminal a propósito."
                />
                <UsersTabs filters={filters} counts={counts} />
                {users !== null && people !== null && permissions !== null && (
                    <UsersTeamArea
                        team={users}
                        people={people}
                        permissions={permissions}
                        filters={filters}
                    />
                )}
                {users !== null && people === null && (
                    <UsersList users={users} filters={filters} />
                )}
                {users === null && permissions !== null && (
                    <PermissionsTable permissions={permissions} />
                )}
            </div>
        </>
    );
}
