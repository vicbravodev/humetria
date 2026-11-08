import { MapPin, Plus } from 'lucide-react';
import { useState } from 'react';
import ZoneDialog from '@/components/attendance/zone-dialog';
import ZoneRow from '@/components/attendance/zone-row';
import EmptyState from '@/components/humetria/empty-state';
import Pagination from '@/components/humetria/pagination';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { AttendanceZone, Paginated } from '@/types';

/** A center's zones, where its people may mark with their location; none yet is the first thing to fix. */
export default function CenterZones({
    zones,
    centerId,
    canManage,
}: {
    zones: Paginated<AttendanceZone>;
    centerId: string;
    canManage: boolean;
}) {
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
                    action={add}
                >
                    {canManage
                        ? 'Sin una zona nadie puede marcar aquí con su ubicación. Agrega la primera desde el lugar con «Usar mi ubicación actual».'
                        : 'Sin una zona nadie puede marcar aquí con su ubicación. Pide a Recursos Humanos que agregue la primera.'}
                </EmptyState>
            ) : (
                <>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Zona</TableHead>
                                <TableHead>Radio</TableHead>
                                {canManage && (
                                    <>
                                        <TableHead>
                                            Centro del círculo
                                        </TableHead>
                                        <TableHead>
                                            <span className="sr-only">
                                                Acciones
                                            </span>
                                        </TableHead>
                                    </>
                                )}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {zones.data.map((zone) => (
                                <ZoneRow
                                    key={zone.id}
                                    zone={zone}
                                    centerId={centerId}
                                    canManage={canManage}
                                />
                            ))}
                        </TableBody>
                    </Table>
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
