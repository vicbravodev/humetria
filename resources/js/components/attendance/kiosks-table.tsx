import KioskRow from '@/components/attendance/kiosk-row';
import {
    Table,
    TableBody,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { KioskScreen } from '@/types';

/** A page of a center's QR screens: state, when issued and last seen, and for whoever may, the way to issue again or revoke. */
export default function KiosksTable({
    kiosks,
    canManage,
    active,
}: {
    kiosks: KioskScreen[];
    canManage: boolean;
    active: number;
}) {
    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Estado</TableHead>
                    <TableHead>Emitida</TableHead>
                    <TableHead>Última señal</TableHead>
                    {canManage && (
                        <TableHead>
                            <span className="sr-only">Acciones</span>
                        </TableHead>
                    )}
                </TableRow>
            </TableHeader>
            <TableBody>
                {kiosks.map((screen) => (
                    <KioskRow
                        key={screen.id}
                        screen={screen}
                        canManage={canManage}
                        active={active}
                    />
                ))}
            </TableBody>
        </Table>
    );
}
