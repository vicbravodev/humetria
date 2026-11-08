import CenterFilter from '@/components/attendance/center-filter';
import DevicesTable from '@/components/attendance/devices-table';
import CountTabs from '@/components/humetria/count-tabs';
import SearchField from '@/components/humetria/search-field';
import { useDevicesFilters } from '@/hooks/use-devices-filters';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { devicesQuery } from '@/lib/attendance/devices';
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
    const current = { ...filters, center: center?.id ?? '' };
    const { text, type, filter } = useDevicesFilters(tenantSlug, current);

    return (
        <section aria-labelledby="personas" className="flex flex-col gap-4">
            <h2 id="personas" className="sr-only">
                Personas y sus dispositivos
            </h2>
            <div className="flex flex-wrap items-center justify-between gap-4">
                <div className="flex w-full flex-col gap-3 sm:flex-row sm:items-center lg:w-auto">
                    <SearchField
                        label="Buscar por nombre"
                        value={text}
                        onChange={type}
                        className="w-full sm:max-w-[320px]"
                    />
                    <CenterFilter
                        id="devices-center"
                        center={center}
                        onChange={(id) => filter({ center: id })}
                    />
                </div>
                <CountTabs
                    label="Estado de los dispositivos"
                    tabs={TABS.map((tab) => ({
                        label: tab.label,
                        count: counts[tab.status],
                        href: index(tenantSlug, {
                            query: devicesQuery({
                                ...current,
                                status: tab.status,
                            }),
                        }),
                        active: filters.status === tab.status,
                    }))}
                />
            </div>
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
