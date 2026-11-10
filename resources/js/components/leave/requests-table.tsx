import Initials from '@/components/humetria/initials';
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
import { daysLine } from '@/lib/leave/queue';
import type { LeaveQueueRequest } from '@/types';

/** The requests of a tab, newest need first: who, whose it was to answer, the dates and what the days were. */
export default function RequestsTable({
    requests,
}: {
    requests: LeaveQueueRequest[];
}) {
    return (
        <StackedTable>
            <TableCaption className="sr-only">
                Solicitudes de vacaciones y permisos
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Persona</TableHead>
                    <TableHead>Fechas</TableHead>
                    <TableHead>Días</TableHead>
                    <TableHead>Estado</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {requests.map((request) => (
                    <TableRow key={request.id}>
                        <TableCell className="whitespace-normal max-md:w-full">
                            <div className="flex items-center gap-3">
                                <Initials name={request.name} />
                                <div className="min-w-36">
                                    <p className="font-medium">
                                        {request.name}
                                    </p>
                                    <p className="text-caption text-muted-foreground">
                                        {request.type ?? 'Vacaciones'} ·{' '}
                                        {request.manager === null
                                            ? 'Sin jefe'
                                            : `Jefe: ${request.manager}`}
                                    </p>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell className="whitespace-normal">
                            {dateSpan(request.startsOn, request.endsOn)}
                            <span className="block text-caption text-muted-foreground">
                                {workingDaysOf(request.workingDays)}
                            </span>
                        </TableCell>
                        <TableCell className="whitespace-normal text-muted-foreground">
                            {daysLine(request)}
                        </TableCell>
                        <TableCell className="whitespace-normal">
                            <div className="flex flex-col items-start gap-1">
                                <Badge variant={statusTone(request.status)}>
                                    {request.statusLabel}
                                </Badge>
                                {request.reason !== null && (
                                    <span className="text-caption text-muted-foreground">
                                        {request.reason}
                                    </span>
                                )}
                            </div>
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </StackedTable>
    );
}
