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
import type { SalaryImportSummary } from '@/types';

/** Every row of a file with a problem, as the spreadsheet numbers it, so the file can be fixed and uploaded again. */
export default function ImportProblems({
    summary,
}: {
    summary: SalaryImportSummary;
}) {
    return (
        <section aria-labelledby="import-problems" className="grid gap-3">
            <div>
                <h2
                    id="import-problems"
                    className="text-lg leading-[26px] font-semibold"
                >
                    Corrige {countOf(summary.errors.length, 'fila', 'filas')}
                </h2>
                <p role="alert" className="text-sm text-muted-foreground">
                    No se importó nada: corrige el archivo y vuelve a subirlo.
                </p>
            </div>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead className="w-24">Fila</TableHead>
                        <TableHead>Qué falta</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {summary.errors.map(({ row, messages }) => (
                        <TableRow key={row}>
                            <TableCell className="align-top text-muted-foreground">
                                Fila {row}
                            </TableCell>
                            <TableCell className="whitespace-normal">
                                <ul className="grid gap-1">
                                    {messages.map((message) => (
                                        <li
                                            key={message}
                                            className="flex gap-1.5 text-sm text-destructive"
                                        >
                                            <CircleAlert
                                                aria-hidden
                                                className="mt-0.5 size-3.5 shrink-0"
                                            />
                                            {message}
                                        </li>
                                    ))}
                                </ul>
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </section>
    );
}
