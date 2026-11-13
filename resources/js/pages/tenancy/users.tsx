import { Head } from '@inertiajs/react';
import { Users } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import PageHeader from '@/components/humetria/page-header';
import Pagination from '@/components/humetria/pagination';
import UsersTable from '@/components/tenancy/users-table';
import UsersToolbar from '@/components/tenancy/users-toolbar';
import { countOf } from '@/lib/plural';
import type { Paginated, PlatformUserFilters, PlatformUserRow } from '@/types';

export default function PlatformUsers({
    users,
    filters,
}: {
    users: Paginated<PlatformUserRow>;
    filters: PlatformUserFilters;
}) {
    return (
        <>
            <Head title="Usuarios" />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    title="Usuarios"
                    description={`${countOf(users.total, 'persona', 'personas')} con cuenta. Ayuda a quien no entra y da o quita el rol de soporte; crear un superadmin sigue siendo por terminal.`}
                />
                <UsersToolbar filters={filters} />
                {users.data.length === 0 ? (
                    <EmptyState
                        icon={Users}
                        title="Nadie coincide con tu búsqueda"
                    >
                        Prueba con otro nombre o con una parte del correo.
                    </EmptyState>
                ) : (
                    <UsersTable users={users.data} />
                )}
                <Pagination
                    previous="Ver más recientes"
                    next="Ver más antiguos"
                    page={users}
                    label="Páginas de usuarios"
                />
            </div>
        </>
    );
}
