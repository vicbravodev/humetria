import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { LeaveImportSummary } from '@/types';

/** The periods that would be loaded, by person. */
export default function ImportReady({
    rows,
}: {
    rows: LeaveImportSummary['readyRows'];
}) {
    return (
        <section aria-labelledby="import-ready" className="grid gap-3">
            <h2
                id="import-ready"
                className="text-lg leading-[26px] font-semibold"
            >
                Lo que se carga
            </h2>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead className="w-24">Fila</TableHead>
                        <TableHead>Persona</TableHead>
                        <TableHead>Periodo</TableHead>
                        <TableHead>Días pendientes</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {rows.map((row) => (
                        <TableRow key={row.line}>
                            <TableCell className="text-muted-foreground">
                                Fila {row.line}
                            </TableCell>
                            <TableCell className="font-medium whitespace-normal">
                                {row.name}
                            </TableCell>
                            <TableCell className="tabular-nums">
                                {row.year}
                            </TableCell>
                            <TableCell className="tabular-nums">
                                {row.days}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </section>
    );
}
