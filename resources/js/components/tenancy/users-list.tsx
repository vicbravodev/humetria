import { Users } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import Pagination from '@/components/humetria/pagination';
import UsersTable from '@/components/tenancy/users-table';
import UsersToolbar from '@/components/tenancy/users-toolbar';
import type { Paginated, PlatformUserFilters, PlatformUserRow } from '@/types';

/** The people of a view of «Equipo Humetría», searched and paged on the server. */
export default function UsersList({
    users,
    filters,
}: {
    users: Paginated<PlatformUserRow>;
    filters: PlatformUserFilters;
}) {
    return (
        <>
            <UsersToolbar filters={filters} />
            {users.data.length === 0 ? (
                <EmptyState icon={Users} title="Nadie coincide con tu búsqueda">
                    Prueba con otro nombre o con una parte del correo.
                </EmptyState>
            ) : (
                <UsersTable users={users.data} team={filters.tab === 'team'} />
            )}
            <Pagination
                previous="Ver más recientes"
                next="Ver más antiguos"
                page={users}
                label="Páginas de personas"
            />
        </>
    );
}
