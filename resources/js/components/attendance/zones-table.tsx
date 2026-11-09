import ZoneRow from '@/components/attendance/zone-row';
import {
    Table,
    TableBody,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { AttendanceZone } from '@/types';

/** A page of a center's zones: name and radius, and for whoever may change them, where each is and the way to change it. */
export default function ZonesTable({
    zones,
    centerId,
    canManage,
    tolerance,
}: {
    zones: AttendanceZone[];
    centerId: string;
    canManage: boolean;
    tolerance: number;
}) {
    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Zona</TableHead>
                    <TableHead>Radio</TableHead>
                    {canManage && (
                        <>
                            <TableHead>Centro del círculo</TableHead>
                            <TableHead>
                                <span className="sr-only">Acciones</span>
                            </TableHead>
                        </>
                    )}
                </TableRow>
            </TableHeader>
            <TableBody>
                {zones.map((zone) => (
                    <ZoneRow
                        key={zone.id}
                        zone={zone}
                        centerId={centerId}
                        canManage={canManage}
                        tolerance={tolerance}
                    />
                ))}
            </TableBody>
        </Table>
    );
}
