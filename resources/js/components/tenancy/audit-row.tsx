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
        <TableRow
            className={cn(
                // On a phone each entry is a card: time and who on top, the action under them, then the company.
                'max-md:grid max-md:grid-cols-[auto_minmax(0,1fr)] max-md:gap-x-3 max-md:gap-y-1 max-md:px-4 max-md:py-3',
                chosen && 'bg-accent hover:bg-accent',
            )}
        >
            <TableCell className="w-px text-muted-foreground tabular-nums max-md:h-auto max-md:w-auto max-md:p-0">
                {entry.time}
            </TableCell>
            <TableCell className="min-w-36 font-medium whitespace-normal max-md:h-auto max-md:min-w-0 max-md:p-0">
                {entry.actor === null
                    ? 'Sistema'
                    : entry.impersonator === null
                      ? entry.actor.name
                      : `${entry.impersonator.name} como ${entry.actor.name}`}
                {entry.actor !== null && (
                    <span className="block text-caption font-normal text-muted-foreground md:hidden">
                        {entry.actor.email}
                    </span>
                )}
            </TableCell>
            <TableCell className="whitespace-normal max-md:col-span-2 max-md:h-auto max-md:p-0">
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
            <TableCell className="whitespace-normal max-md:col-span-2 max-md:h-auto max-md:p-0 max-md:text-muted-foreground">
                {entry.company?.name ?? (
                    <span className="text-muted-foreground">Plataforma</span>
                )}
            </TableCell>
            <TableCell className="text-muted-foreground max-md:hidden">
                {entry.origin}
            </TableCell>
        </TableRow>
    );
}
