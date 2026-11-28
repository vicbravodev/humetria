import Pagination from '@/components/humetria/pagination';
import UsersHelpRow from '@/components/tenancy/users-help-row';
import UsersToolbar from '@/components/tenancy/users-toolbar';
import type { Paginated, PlatformUserFilters, PlatformUserRow } from '@/types';

/** «Personas con cuenta: ayuda rápida» (tablero Equipo): find anyone with an account and help them back in. */
export default function UsersHelp({
    people,
    filters,
}: {
    people: Paginated<PlatformUserRow>;
    filters: PlatformUserFilters;
}) {
    return (
        <section aria-labelledby="users-help" className="card">
            <div className="flex flex-wrap items-center gap-3 border-b px-6 py-4">
                <h2 id="users-help" className="flex-1 font-semibold">
                    Personas con cuenta: ayuda rápida
                </h2>
                <UsersToolbar filters={filters} />
            </div>
            {people.data.length === 0 ? (
                <p className="px-6 py-5 text-muted-foreground">
                    Nadie coincide con tu búsqueda. Prueba con otro nombre o con
                    una parte del correo.
                </p>
            ) : (
                <ul>
                    {people.data.map((user) => (
                        <UsersHelpRow key={user.id} user={user} />
                    ))}
                </ul>
            )}
            <div className="border-t px-6 py-3 empty:hidden">
                <Pagination
                    previous="Ver más recientes"
                    next="Ver más antiguos"
                    page={people}
                    label="Páginas de personas con cuenta"
                    pinned={false}
                />
            </div>
        </section>
    );
}
