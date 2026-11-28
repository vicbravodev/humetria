import Notice from '@/components/humetria/notice';
import Pagination from '@/components/humetria/pagination';
import PermissionsCard from '@/components/tenancy/permissions-card';
import UsersHelp from '@/components/tenancy/users-help';
import UsersTeamTable from '@/components/tenancy/users-team-table';
import type {
    Paginated,
    PlatformPermissionRow,
    PlatformUserFilters,
    PlatformUserRow,
} from '@/types';

/**
 * The team view (tablero Equipo): the team and the quick help for anyone with an account at the left, what each role
 * may do and what support never does at the right; one column when there is no room for two.
 */
export default function UsersTeamArea({
    team,
    people,
    permissions,
    filters,
}: {
    team: Paginated<PlatformUserRow>;
    people: Paginated<PlatformUserRow>;
    permissions: PlatformPermissionRow[];
    filters: PlatformUserFilters;
}) {
    return (
        <div className="flex flex-wrap items-start gap-6">
            <div className="flex min-w-0 flex-[999_1_560px] flex-col gap-6">
                <div className="flex flex-col gap-2">
                    <UsersTeamTable users={team.data} />
                    <Pagination
                        previous="Ver más recientes"
                        next="Ver más antiguos"
                        page={team}
                        label="Páginas del equipo"
                        pinned={false}
                    />
                </div>
                <UsersHelp people={people} filters={filters} />
            </div>
            <div className="flex max-w-full flex-[1_1_320px] flex-col gap-6">
                <PermissionsCard permissions={permissions} />
                <Notice>
                    Soporte ve la consola y entra a empresas con motivo; nunca
                    ve respuestas ni cambia planes, precios ni cobros.
                </Notice>
            </div>
        </div>
    );
}
