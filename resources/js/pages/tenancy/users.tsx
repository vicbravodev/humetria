import { Head } from '@inertiajs/react';
import { Users } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import PageHeader from '@/components/humetria/page-header';
import Pagination from '@/components/humetria/pagination';
import UsersTable from '@/components/tenancy/users-table';
import UsersTabs from '@/components/tenancy/users-tabs';
import UsersToolbar from '@/components/tenancy/users-toolbar';
import type {
    Paginated,
    PlatformUserCounts,
    PlatformUserFilters,
    PlatformUserRow,
} from '@/types';

export default function PlatformUsers({
    users,
    counts,
    filters,
}: {
    users: Paginated<PlatformUserRow>;
    counts: PlatformUserCounts;
    filters: PlatformUserFilters;
}) {
    return (
        <>
            <Head title="Equipo Humetría" />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    title="Equipo Humetría"
                    description="Quién del equipo entra a la consola y qué puede hacer, y ayuda para cualquier persona con cuenta que no logra entrar. El rol de soporte se da y se quita aquí; crear un superadmin sigue siendo por terminal."
                />
                <div className="flex flex-col gap-4">
                    <UsersTabs filters={filters} counts={counts} />
                    <UsersToolbar filters={filters} />
                </div>
                {users.data.length === 0 ? (
                    <EmptyState
                        icon={Users}
                        title="Nadie coincide con tu búsqueda"
                    >
                        Prueba con otro nombre o con una parte del correo.
                    </EmptyState>
                ) : (
                    <UsersTable
                        users={users.data}
                        team={filters.tab === 'team'}
                    />
                )}
                <Pagination
                    previous="Ver más recientes"
                    next="Ver más antiguos"
                    page={users}
                    label="Páginas de personas"
                />
            </div>
        </>
    );
}
