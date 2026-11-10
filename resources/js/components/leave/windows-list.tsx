import Initials from '@/components/humetria/initials';
import StackedTable from '@/components/humetria/stacked-table';
import {
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dateSpan } from '@/lib/leave/format';
import { countOf } from '@/lib/plural';
import type { LeaveSummaryRow } from '@/types';

/**
 * The periods that still have days and whose window is about to close or is past it, or whose eighteen months ended: they
 * stay in the balance. The second date of each row is the one that makes it appear in the list.
 */
export default function WindowsList({
    rows,
    prescribable,
}: {
    rows: LeaveSummaryRow[];
    prescribable: boolean;
}) {
    return (
        <StackedTable>
            <TableCaption className="sr-only">
                Periodos con días por disfrutar, del plazo más antiguo al más
                reciente
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Persona</TableHead>
                    <TableHead>Periodo</TableHead>
                    <TableHead>Días por disfrutar</TableHead>
                    <TableHead>
                        {prescribable
                            ? 'Del aniversario a los 18 meses'
                            : 'Del aniversario al fin del plazo'}
                    </TableHead>
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
                        <TableCell>{row.label}</TableCell>
                        <TableCell className="tabular-nums">
                            {countOf(row.days, 'día', 'días')}
                        </TableCell>
                        <TableCell className="whitespace-normal text-muted-foreground">
                            {dateSpan(row.startsOn, row.endsOn)}
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </StackedTable>
    );
}
