import { Link } from '@inertiajs/react';
import { MapPin, Plus } from 'lucide-react';
import { useState } from 'react';
import ZoneDialog from '@/components/attendance/zone-dialog';
import ZoneMap from '@/components/attendance/zone-map';
import ZonesTable from '@/components/attendance/zones-table';
import EmptyState from '@/components/humetria/empty-state';
import Pagination from '@/components/humetria/pagination';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { mapZones } from '@/lib/attendance/zone-map';
import { show as policy } from '@/routes/tenant/attendance/policy';
import type { AttendanceZone, Paginated } from '@/types';

/** A center's zones, where its people may mark with their location; none yet is the first thing to fix. */
export default function CenterZones({
    zones,
    centerId,
    canManage,
    tolerance,
}: {
    zones: Paginated<AttendanceZone>;
    centerId: string;
    canManage: boolean;
    tolerance: number;
}) {
    const tenantSlug = useTenantSlug();
    const [adding, setAdding] = useState(false);
    const add = canManage ? (
        <Button onClick={() => setAdding(true)}>
            <Plus aria-hidden />
            Agregar zona
        </Button>
    ) : undefined;

    return (
        <section aria-labelledby="zonas" className="flex flex-col gap-4">
            <div className="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 id="zonas" className="text-xl leading-7 font-semibold">
                        Zonas
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Un círculo por lugar: quien marca con su teléfono debe
                        estar dentro de alguno.
                    </p>
                </div>
                {zones.total > 0 && add}
            </div>
            {zones.total === 0 ? (
                <EmptyState
                    icon={MapPin}
                    title="Este centro aún no tiene zonas"
                    action={
                        canManage ? (
                            <div className="flex flex-wrap justify-center gap-2.5">
                                {add}
                                <Button asChild variant="outline">
                                    <Link href={policy(tenantSlug)}>
                                        Cambiar política
                                    </Link>
                                </Button>
                            </div>
                        ) : undefined
                    }
                >
                    {canManage
                        ? 'Sin una zona nadie puede marcar aquí con su ubicación. Agrega la primera desde el lugar con «Usar mi ubicación actual».'
                        : 'Sin una zona nadie puede marcar aquí con su ubicación. Pide a Recursos Humanos que agregue la primera.'}
                </EmptyState>
            ) : (
                <>
                    {canManage && <ZoneMap zones={mapZones(zones.data)} />}
                    <ZonesTable
                        zones={zones.data}
                        centerId={centerId}
                        canManage={canManage}
                        tolerance={tolerance}
                    />
                    <Pagination
                        page={zones}
                        label="Páginas de zonas"
                        pinned={false}
                    />
                </>
            )}
            {adding && (
                <ZoneDialog
                    open={adding}
                    onOpenChange={setAdding}
                    centerId={centerId}
                    zone={null}
                />
            )}
        </section>
    );
}
