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
import { dateSpan } from '@/lib/leave/format';
import { entryDays } from '@/lib/leave/record';
import type { LeaveLedgerEntry, Paginated } from '@/types';

/** The ledger of the person: every movement of their days, newest first. It only grows: a correction is a new line. */
export default function LedgerTable({
    entries,
}: {
    entries: Paginated<LeaveLedgerEntry>;
}) {
    return (
        <>
            <StackedTable>
                <TableCaption className="sr-only">
                    Movimientos de los días de vacaciones
                </TableCaption>
                <TableHeader>
                    <TableRow>
                        <TableHead>Fecha</TableHead>
                        <TableHead>Movimiento</TableHead>
                        <TableHead>Días</TableHead>
                        <TableHead>Nota</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {entries.data.map((entry) => (
                        <TableRow key={entry.id}>
                            <TableCell className="whitespace-normal">
                                {dateSpan(entry.on, entry.on)}
                            </TableCell>
                            <TableCell className="whitespace-normal">
                                <p className="font-medium">{entry.kindLabel}</p>
                                <p className="text-caption text-muted-foreground">
                                    {entry.period === null
                                        ? 'Adelantados'
                                        : `Periodo ${entry.period}`}{' '}
                                    ·{' '}
                                    {entry.tranche === 'statutory'
                                        ? 'Tramo legal'
                                        : 'Tramo adicional'}
                                </p>
                            </TableCell>
                            <TableCell>
                                <Badge
                                    variant={
                                        entry.days < 0 ? 'secondary' : 'success'
                                    }
                                >
                                    {entryDays(entry.days)}
                                </Badge>
                            </TableCell>
                            <TableCell className="whitespace-normal text-muted-foreground">
                                {entry.note ?? '—'}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </StackedTable>
            <Pagination
                page={entries}
                label="Páginas de movimientos"
                pinned={false}
            />
        </>
    );
}
