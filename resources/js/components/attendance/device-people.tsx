import DevicesTable from '@/components/attendance/devices-table';
import ListToolbar from '@/components/attendance/list-toolbar';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index } from '@/routes/tenant/attendance/devices';
import type { AttendanceDevicesProps, DeviceView } from '@/types';

const TABS: { status: DeviceView; label: string }[] = [
    { status: 'all', label: 'Todas' },
    { status: 'linked', label: 'Con dispositivo' },
    { status: 'unlinked', label: 'Sin dispositivo' },
    { status: 'revoked', label: 'Revocados' },
];

/** The people and their devices: a tab per state, searched by name, narrowed to a center. */
export default function DevicePeople({
    center,
    filters,
    counts,
    people,
    canManage,
}: AttendanceDevicesProps) {
    const tenantSlug = useTenantSlug();

    return (
        <section aria-labelledby="personas" className="flex flex-col gap-4">
            <h2 id="personas" className="sr-only">
                Personas y sus dispositivos
            </h2>
            <ListToolbar
                page={{
                    url: index.url(tenantSlug),
                    href: (options) => index(tenantSlug, options),
                }}
                label="Estado de los dispositivos"
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
            <DevicesTable
                people={people}
                status={filters.status}
                searching={filters.q !== ''}
                canManage={canManage}
            />
        </section>
    );
}
