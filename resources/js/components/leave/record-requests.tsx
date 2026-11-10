import { CalendarCheck } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import Pagination from '@/components/humetria/pagination';
import StackedTable from '@/components/humetria/stacked-table';
import { Badge } from '@/components/ui/badge';
import {
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dateSpan, statusTone, workingDaysOf } from '@/lib/leave/format';
import type { LeaveRecordRequest, Paginated } from '@/types';

/** The requests of the person with who answered each and when. */
export default function RecordRequests({
    requests,
}: {
    requests: Paginated<LeaveRecordRequest>;
}) {
    if (requests.total === 0) {
        return (
            <EmptyState icon={CalendarCheck} title="Sin solicitudes">
                Aquí aparecerán sus vacaciones y permisos.
            </EmptyState>
        );
    }

    return (
        <>
            <StackedTable>
                <TableCaption className="sr-only">
                    Solicitudes de vacaciones y permisos de la persona
                </TableCaption>
                <TableHeader>
                    <TableRow>
                        <TableHead>Fechas</TableHead>
                        <TableHead>Estado</TableHead>
                        <TableHead>Respondió</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {requests.data.map((request) => (
                        <TableRow key={request.id}>
                            <TableCell className="whitespace-normal max-md:w-full">
                                <p className="font-medium">
                                    {dateSpan(request.startsOn, request.endsOn)}
                                </p>
                                <p className="text-caption text-muted-foreground">
                                    {request.type ?? 'Vacaciones'} ·{' '}
                                    {workingDaysOf(request.workingDays)}
                                    {request.note === null
                                        ? ''
                                        : ` · «${request.note}»`}
                                </p>
                            </TableCell>
                            <TableCell>
                                <Badge variant={statusTone(request.status)}>
                                    {request.statusLabel}
                                </Badge>
                            </TableCell>
                            <TableCell className="whitespace-normal text-muted-foreground">
                                {request.decidedBy === null ||
                                request.decidedOn === null
                                    ? 'Sin respuesta'
                                    : `${request.decidedBy} · ${dateSpan(request.decidedOn, request.decidedOn)}`}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </StackedTable>
            <Pagination
                page={requests}
                label="Páginas de solicitudes"
                pinned={false}
            />
        </>
    );
}
