import Initials from '@/components/humetria/initials';
import { Badge } from '@/components/ui/badge';
import { TableCell, TableRow } from '@/components/ui/table';
import { dateSpan, statusTone } from '@/lib/leave/format';
import { reasonLine } from '@/lib/leave/overview';
import { sinceLabel } from '@/lib/leave/queue';
import { cn } from '@/lib/utils';
import type { LeaveQueueRequest } from '@/types';

/**
 * A request of RH's queue: who, which days, how many and since when it waits. The whole row selects it, to read and answer
 * it in the decision panel; the one selected is tinted.
 */
export default function QueueRow({
    request,
    selected,
    onSelect,
}: {
    request: LeaveQueueRequest;
    selected: boolean;
    onSelect: () => void;
}) {
    return (
        <TableRow
            data-state={selected ? 'selected' : undefined}
            onClick={onSelect}
            className={cn('cursor-pointer', selected && 'bg-accent')}
        >
            <TableCell className="h-14 whitespace-normal max-md:w-full">
                <div className="flex items-center gap-3">
                    <Initials name={request.name} />
                    <div className="min-w-36">
                        <button
                            type="button"
                            aria-pressed={selected}
                            onClick={onSelect}
                            className="touch-hit text-left font-medium outline-none focus-visible:underline"
                        >
                            {request.name}
                        </button>
                        <p className="text-caption text-muted-foreground">
                            {request.type ?? 'Vacaciones'}
                            {request.status !== 'requested' && (
                                <>
                                    {' · '}
                                    <Badge
                                        variant={statusTone(request.status)}
                                        className="align-middle"
                                    >
                                        {request.statusLabel}
                                    </Badge>
                                </>
                            )}
                        </p>
                        {request.reason !== null && (
                            <p className="text-caption text-muted-foreground">
                                {reasonLine(request.reason, request.manager)}
                            </p>
                        )}
                    </div>
                </div>
            </TableCell>
            <TableCell className="whitespace-normal">
                {dateSpan(request.startsOn, request.endsOn)}
            </TableCell>
            <TableCell className="text-right tabular-nums md:w-16">
                {request.workingDays}
                <span className="md:sr-only"> días laborables</span>
            </TableCell>
            <TableCell className="whitespace-normal text-muted-foreground">
                <span className="md:sr-only">Espera desde el </span>
                {sinceLabel(request.requestedOn)}
            </TableCell>
        </TableRow>
    );
}
