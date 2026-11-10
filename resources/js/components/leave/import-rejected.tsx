import { CircleAlert } from 'lucide-react';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { countOf } from '@/lib/plural';
import type { LeaveImportSummary } from '@/types';

/** Each row that stays out, with the reason in Spanish; past the first rows, how many more there are. */
export default function ImportRejected({
    summary,
}: {
    summary: LeaveImportSummary;
}) {
    return (
        <section aria-labelledby="import-rejected" className="grid gap-3">
            <h2
                id="import-rejected"
                className="text-lg leading-[26px] font-semibold"
            >
                Por qué se quedan fuera
            </h2>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead className="w-24">Fila</TableHead>
                        <TableHead>Motivo</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {summary.rejectedRows.map((row) => (
                        <TableRow key={row.line}>
                            <TableCell className="text-muted-foreground">
                                Fila {row.line}
                            </TableCell>
                            <TableCell className="whitespace-normal">
                                <span className="flex gap-1.5 text-sm text-destructive">
                                    <CircleAlert
                                        aria-hidden
                                        className="mt-0.5 size-3.5 shrink-0"
                                    />
                                    {row.reason}
                                </span>
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
            {summary.hiddenRejected > 0 && (
                <p className="text-sm text-muted-foreground">
                    Y {countOf(summary.hiddenRejected, 'fila más', 'filas más')}
                    : corrige estas y vuelve a revisar el archivo.
                </p>
            )}
        </section>
    );
}
