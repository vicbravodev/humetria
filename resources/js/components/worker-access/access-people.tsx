import ListToolbar from '@/components/list-toolbar';
import AccessTable from '@/components/worker-access/access-table';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index } from '@/routes/tenant/worker-access';
import type { AccessView, WorkerAccessPageProps } from '@/types';

const TABS: { status: AccessView; label: string }[] = [
    { status: 'all', label: 'Todas' },
    { status: 'with_key', label: 'Con llave' },
    { status: 'invited', label: 'Invitadas' },
    { status: 'not_invited', label: 'Sin invitar' },
];

/** The people and their keys: a tab per state, searched by name, narrowed to a center. */
export default function AccessPeople({
    center,
    filters,
    counts,
    people,
    canManage,
}: WorkerAccessPageProps) {
    const tenantSlug = useTenantSlug();

    return (
        <section aria-labelledby="personas" className="flex flex-col gap-4">
            <h2 id="personas" className="sr-only">
                Personas y sus llaves
            </h2>
            <ListToolbar
                page={{
                    url: index.url(tenantSlug),
                    href: (options) => index(tenantSlug, options),
                }}
                label="Estado del acceso"
                tabs={TABS}
                center={center}
                filters={filters}
                counts={counts}
            />
            <p className="text-sm text-pretty text-muted-foreground">
                <strong className="font-medium text-foreground">
                    Llave sincronizada:
                </strong>{' '}
                vive en la cuenta de llaves de acceso de la persona (iCloud,
                Google), no en un solo teléfono.{' '}
                <strong className="font-medium text-foreground">
                    Solo este dispositivo:
                </strong>{' '}
                la llave no sale de ese equipo.
            </p>
            <AccessTable
                people={people}
                status={filters.status}
                searching={filters.q !== ''}
                canManage={canManage}
            />
        </section>
    );
}
