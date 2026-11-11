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
import { dateSpan, workingDaysOf } from '@/lib/leave/format';
import type { LeaveSummaryRow } from '@/types';

/** Who is out today: their dates and whether it is vacations or a leave; never which leave. */
export default function OutList({ rows }: { rows: LeaveSummaryRow[] }) {
    return (
        <StackedTable>
            <TableCaption className="sr-only">
                Personas fuera hoy, por fecha de salida
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Persona</TableHead>
                    <TableHead>Motivo</TableHead>
                    <TableHead>Fechas</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {rows.map((row) => (
                    <TableRow key={row.id}>
                        <TableCell className="whitespace-normal max-md:w-full">
                            <div className="flex items-center gap-3">
                                <Initials name={row.name} />
                                <p className="font-medium">{row.name}</p>
                            </div>
                        </TableCell>
                        <TableCell>
                            <Badge variant="secondary">{row.label}</Badge>
                        </TableCell>
                        <TableCell className="whitespace-normal text-muted-foreground">
                            {dateSpan(row.startsOn, row.endsOn)} ·{' '}
                            {workingDaysOf(row.days)}
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </StackedTable>
    );
}
