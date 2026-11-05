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
import { countOf } from '@/lib/plural';
import type { CompareRow, CompareScenario } from '@/types';

/** The scenarios as columns and the concepts of the cost as rows; the total of the first year closes each column. */
export default function ScenarioCompareTable({
    scenarios,
    rows,
}: {
    scenarios: CompareScenario[];
    rows: CompareRow[];
}) {
    return (
        <Table>
            <TableCaption className="sr-only">
                Costo del primer año de cada escenario por concepto
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Concepto</TableHead>
                    {scenarios.map((scenario) => (
                        <TableHead key={scenario.id} className="text-right">
                            {scenario.name}
                        </TableHead>
                    ))}
                </TableRow>
            </TableHeader>
            <TableBody>
                {rows.map((row) => (
                    <TableRow key={`${row.type}:${row.key}`}>
                        <TableCell className="whitespace-normal">
                            {row.label}
                        </TableCell>
                        {row.cells.map((cell, index) => (
                            <TableCell
                                key={scenarios[index]?.id ?? index}
                                className="text-right tabular-nums"
                            >
                                {cell}
                            </TableCell>
                        ))}
                    </TableRow>
                ))}
            </TableBody>
            <TableFooter>
                <TableRow>
                    <TableCell className="font-semibold">
                        Costo del primer año
                    </TableCell>
                    {scenarios.map((scenario) => (
                        <TableCell
                            key={scenario.id}
                            className="text-right font-semibold tabular-nums"
                        >
                            {scenario.first_year_text}
                            <span className="block text-sm font-normal text-muted-foreground">
                                {countOf(
                                    scenario.people,
                                    'persona costeada',
                                    'personas costeadas',
                                )}
                            </span>
                        </TableCell>
                    ))}
                </TableRow>
                <TableRow>
                    <TableCell>Costo anual recurrente</TableCell>
                    {scenarios.map((scenario) => (
                        <TableCell
                            key={scenario.id}
                            className="text-right tabular-nums"
                        >
                            {scenario.recurring_text}
                        </TableCell>
                    ))}
                </TableRow>
            </TableFooter>
        </Table>
    );
}
