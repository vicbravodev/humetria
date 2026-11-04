import FollowUpLine from '@/components/interventions/follow-up-line';
import {
    Table,
    TableBody,
    TableCaption,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { ProgramRow, ProgramTools } from '@/types';

/**
 * The evaluation after the measures (num. 8.4 e, «en su caso»), one per row: planned for a day, then a note with what it
 * found. The comparison against the earlier evaluation is not automatic: it is the person's reading.
 */
export default function ProgramFollowUp({
    rows,
    campaignId,
    tools,
}: {
    rows: ProgramRow[];
    campaignId: string;
    tools: ProgramTools | null;
}) {
    return (
        <section
            aria-label="Evaluación posterior"
            className="flex flex-col gap-4"
        >
            <p className="max-w-[620px] text-muted-foreground">
                Es opcional: la norma pide la evaluación posterior a las medidas
                «en su caso». Anota para cuándo la programas y qué encontraste.
            </p>
            <Table>
                <TableCaption className="sr-only">
                    Evaluación posterior por fila
                </TableCaption>
                <TableHeader>
                    <TableRow>
                        <TableHead>Fila</TableHead>
                        <TableHead>Programada</TableHead>
                        <TableHead>Se hizo</TableHead>
                        <TableHead>Resultado</TableHead>
                        <TableHead className="w-px">
                            <span className="sr-only">Opciones</span>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {rows.map((row) => (
                        <FollowUpLine
                            key={row.id}
                            row={row}
                            campaignId={campaignId}
                            tools={tools}
                        />
                    ))}
                </TableBody>
            </Table>
        </section>
    );
}
