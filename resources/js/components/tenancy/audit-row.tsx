import { Badge } from '@/components/ui/badge';
import { TableCell, TableRow } from '@/components/ui/table';
import { eventLabel } from '@/lib/audit';
import type { AuditTag } from '@/lib/audit-view';
import { cn } from '@/lib/utils';
import type { AuditEntryRow } from '@/types';

/**
 * One entry of the trail (tablero Bitácora): its time, who, the action with its tag, the company and where it came from.
 * The action opens its detail; the one in the docked detail is marked.
 */
export default function AuditRow({
    entry,
    tag,
    chosen,
    onChoose,
}: {
    entry: AuditEntryRow;
    tag: AuditTag;
    chosen: boolean;
    onChoose: () => void;
}) {
    return (
        <TableRow className={cn(chosen && 'bg-accent hover:bg-accent')}>
            <TableCell className="w-px text-muted-foreground tabular-nums">
                {entry.time}
            </TableCell>
            <TableCell className="min-w-36 font-medium whitespace-normal">
                {entry.actor === null
                    ? 'Sistema'
                    : entry.impersonator === null
                      ? entry.actor.name
                      : `${entry.impersonator.name} como ${entry.actor.name}`}
            </TableCell>
            <TableCell className="whitespace-normal">
                <span className="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <Badge variant={tag.variant}>{tag.label}</Badge>
                    <button
                        type="button"
                        aria-label={`Detalle: ${eventLabel(entry.event)}`}
                        aria-pressed={chosen}
                        onClick={onChoose}
                        className="touch-hit text-left underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    >
                        {eventLabel(entry.event)}
                    </button>
                </span>
            </TableCell>
            <TableCell className="whitespace-normal">
                {entry.company?.name ?? (
                    <span className="text-muted-foreground">Plataforma</span>
                )}
            </TableCell>
            <TableCell className="text-muted-foreground">
                {entry.origin}
            </TableCell>
        </TableRow>
    );
}
