import { Armchair } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import Pagination from '@/components/humetria/pagination';
import SeatRow from '@/components/seating/seat-row';
import {
    Table,
    TableBody,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { Paginated, SeatRow as Seat } from '@/types';

/** The inventory of a center (pantalla Empleados): what each seat is, where, in which condition, with a way to edit it. */
export default function SeatsTable({
    workCenterId,
    seats,
    canManage,
}: {
    workCenterId: string;
    seats: Paginated<Seat>;
    canManage: boolean;
}) {
    if (seats.total === 0) {
        return (
            <EmptyState icon={Armchair} title="Sin asientos registrados">
                Registra los asientos del centro: los de cada puesto y los de
                las áreas designadas para los descansos.
            </EmptyState>
        );
    }

    return (
        <>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Asiento</TableHead>
                        <TableHead>Dónde</TableHead>
                        <TableHead>Estado</TableHead>
                        <TableHead>Características</TableHead>
                        <TableHead className="w-px">
                            <span className="sr-only">Acciones</span>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {seats.data.map((seat) => (
                        <SeatRow
                            key={seat.id}
                            workCenterId={workCenterId}
                            seat={seat}
                            canManage={canManage}
                        />
                    ))}
                </TableBody>
            </Table>
            <Pagination
                page={seats}
                label="Páginas de asientos"
                pinned={false}
            />
        </>
    );
}
