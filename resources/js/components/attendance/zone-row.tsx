import { Pencil } from 'lucide-react';
import { useState } from 'react';
import ZoneDialog from '@/components/attendance/zone-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { TableCell, TableRow } from '@/components/ui/table';
import { placeText, radiusText } from '@/lib/attendance/zone';
import type { AttendanceZone } from '@/types';

/** A zone: its name and radius; where it is and the way to change it, only for whoever may. */
export default function ZoneRow({
    zone,
    centerId,
    canManage,
    tolerance,
}: {
    zone: AttendanceZone;
    centerId: string;
    canManage: boolean;
    tolerance: number;
}) {
    const [open, setOpen] = useState(false);

    return (
        <TableRow>
            <TableCell className="font-medium">{zone.name}</TableCell>
            <TableCell className="tabular-nums">
                <span className="flex flex-wrap items-center gap-2">
                    {radiusText(zone.radius)}
                    {zone.radius < tolerance && (
                        <Badge
                            variant="warning"
                            title={`Es menor que la precisión que aceptas (${radiusText(tolerance)}): una marca con señal débil podría parecer dentro sin estarlo.`}
                        >
                            Menor que la precisión
                        </Badge>
                    )}
                </span>
            </TableCell>
            {canManage && (
                <>
                    <TableCell className="text-muted-foreground tabular-nums">
                        {zone.latitude === undefined ||
                        zone.longitude === undefined
                            ? '—'
                            : placeText(zone.latitude, zone.longitude)}
                    </TableCell>
                    <TableCell className="text-right">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setOpen(true)}
                            aria-label={`Cambiar ${zone.name}`}
                        >
                            <Pencil aria-hidden />
                            Cambiar
                        </Button>
                        {open && (
                            <ZoneDialog
                                open={open}
                                onOpenChange={setOpen}
                                centerId={centerId}
                                zone={zone}
                            />
                        )}
                    </TableCell>
                </>
            )}
        </TableRow>
    );
}
