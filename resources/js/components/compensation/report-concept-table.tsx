import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { ScenarioReport } from '@/types';

const MONEY = 'text-right tabular-nums';

/** Each concept before and after, a 12 months of vigencia, its change and what it adds to the first year, with the total. */
export default function ReportConceptTable({
    report,
}: {
    report: ScenarioReport;
}) {
    const { headline } = report;

    return (
        <Table>
            <TableCaption className="sr-only">
                Detalle del costo por concepto
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Concepto</TableHead>
                    <TableHead className="text-right">Antes</TableHead>
                    <TableHead className="text-right">Después</TableHead>
                    <TableHead className="text-right">Cambio</TableHead>
                    <TableHead className="text-right">
                        Con retroactivo
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {report.concepts.map((concept) => (
                    <TableRow key={`${concept.type}:${concept.key}`}>
                        <TableCell className="min-w-48 whitespace-normal">
                            {concept.label}
                        </TableCell>
                        <TableCell className={`${MONEY} text-muted-foreground`}>
                            {concept.before_text}
                        </TableCell>
                        <TableCell className={MONEY}>
                            {concept.after_text}
                        </TableCell>
                        <TableCell className={MONEY}>
                            {concept.delta_text}
                        </TableCell>
                        <TableCell className={`${MONEY} font-medium`}>
                            {concept.first_year_text}
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
            <TableFooter>
                <TableRow className="font-semibold">
                    <TableCell>Total</TableCell>
                    <TableCell className={MONEY}>
                        {headline.before_text}
                    </TableCell>
                    <TableCell className={MONEY}>
                        {headline.after_text}
                    </TableCell>
                    <TableCell className={MONEY}>
                        {headline.recurring_text}
                    </TableCell>
                    <TableCell className={MONEY}>
                        {headline.first_year_text}
                    </TableCell>
                </TableRow>
            </TableFooter>
        </Table>
    );
}
