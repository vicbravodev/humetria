import CorrectionActions from '@/components/attendance/correction-actions';
import Initials from '@/components/humetria/initials';
import { Badge } from '@/components/ui/badge';
import { TableCell, TableRow } from '@/components/ui/table';
import { shortMoment, waitingFor } from '@/lib/attendance/corrections';
import { markName } from '@/lib/attendance/next-step';
import type { CorrectionRow as Row } from '@/types';

/** One request: who, what they ask against what is there, why, how long it waits, and what to do or how it ended. */
export default function CorrectionRow({
    correction,
    canResolve,
}: {
    correction: Row;
    canResolve: boolean;
}) {
    const pending = correction.status === 'pending';

    return (
        <TableRow>
            <TableCell>
                <div className="flex items-center gap-3">
                    <Initials name={correction.person.name} />
                    <div className="flex min-w-0 flex-col">
                        <span className="font-medium">
                            {correction.person.name}
                        </span>
                        <span className="text-caption text-muted-foreground">
                            {correction.person.jobTitle}
                        </span>
                    </div>
                </div>
            </TableCell>
            <TableCell>
                <span className="block font-medium">
                    {markName(correction.type)}
                </span>
                <span className="block tabular-nums">
                    {shortMoment(correction.requestedAt)}
                </span>
                <span className="block text-caption text-muted-foreground">
                    {correction.previousAt === null
                        ? 'Olvidó marcarla'
                        : `Hora anterior: ${shortMoment(correction.previousAt)}`}
                </span>
            </TableCell>
            <TableCell className="max-w-sm min-w-56 whitespace-normal">
                {correction.reason}
            </TableCell>
            <TableCell className="text-muted-foreground">
                {waitingFor(correction.ageDays)}
            </TableCell>
            <TableCell className="min-w-56 whitespace-normal">
                {pending ? (
                    canResolve ? (
                        <CorrectionActions correction={correction} />
                    ) : (
                        <Badge variant="warning">Por resolver</Badge>
                    )
                ) : (
                    <div className="flex flex-col gap-1">
                        <Badge
                            variant={
                                correction.status === 'approved'
                                    ? 'success'
                                    : 'secondary'
                            }
                        >
                            {correction.status === 'approved'
                                ? 'Aprobada'
                                : 'Rechazada'}
                        </Badge>
                        {correction.note !== null && (
                            <span className="text-caption text-muted-foreground">
                                {correction.note}
                            </span>
                        )}
                    </div>
                )}
            </TableCell>
        </TableRow>
    );
}
