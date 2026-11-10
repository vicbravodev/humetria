import Initials from '@/components/humetria/initials';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { TableCell, TableRow } from '@/components/ui/table';
import { dateSpan, statusTone, workingDaysOf } from '@/lib/leave/format';
import { daysLine } from '@/lib/leave/queue';
import type { LeaveQueueRequest } from '@/types';

/** A request of RH's queue: who, whose it was to answer, the dates, what the days were and its state; «Ver» opens its panel. */
export default function QueueRow({
    request,
    onOpen,
}: {
    request: LeaveQueueRequest;
    onOpen: () => void;
}) {
    return (
        <TableRow>
            <TableCell className="whitespace-normal max-md:w-full">
                <div className="flex items-center gap-3">
                    <Initials name={request.name} />
                    <div className="min-w-36">
                        <p className="font-medium">{request.name}</p>
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
            <TableCell className="text-right">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={onOpen}
                    aria-label={`Ver la solicitud de ${request.name}`}
                >
                    Ver
                </Button>
            </TableCell>
        </TableRow>
    );
}
