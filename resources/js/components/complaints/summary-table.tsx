import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { SummaryCell } from '@/types';

/** One breakdown of the summary: each cell with its number, or «menos de 5» when it is under the minimum of people. */
export default function SummaryTable({
    title,
    cells,
    minimum,
}: {
    title: string;
    cells: SummaryCell[];
    minimum: number;
}) {
    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>{title}</TableHead>
                    <TableHead className="text-right">Casos</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {cells.map((cell) => (
                    <TableRow key={cell.value}>
                        <TableCell className="whitespace-normal">
                            {cell.label}
                        </TableCell>
                        <TableCell className="text-right tabular-nums">
                            {cell.count === null
                                ? `menos de ${minimum}`
                                : cell.count}
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
