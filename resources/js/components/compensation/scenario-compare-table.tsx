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
import type { CompareRow, CompareScenario, CompareTotals } from '@/types';

/** «Primer año por concepto»: the scenarios as columns from the cheapest, the dearest against the one before it at the end. */
export default function ScenarioCompareTable({
    scenarios,
    rows,
    totals,
}: {
    scenarios: CompareScenario[];
    rows: CompareRow[];
    totals: CompareTotals;
}) {
    const last = scenarios.at(-1)?.name ?? '';
    const before = scenarios.at(-2)?.name ?? '';

    return (
        <section aria-labelledby="compare-concepts" className="grid gap-0 card">
            <h2
                id="compare-concepts"
                className="px-6 pt-6 pb-4 text-lg font-semibold"
            >
                Primer año por concepto
            </h2>
            <Table>
                <TableCaption className="sr-only">
                    Costo del primer año de cada escenario por concepto, con lo
                    que separa a los dos más caros
                </TableCaption>
                <TableHeader>
                    <TableRow>
                        <TableHead className="pl-6">Concepto</TableHead>
                        {scenarios.map((scenario) => (
                            <TableHead
                                key={scenario.id}
                                className="text-right whitespace-normal"
                            >
                                {scenario.name}
                            </TableHead>
                        ))}
                        <TableHead className="pr-6 text-right whitespace-normal">
                            Diferencia
                            <span className="sr-only">
                                {' '}
                                entre «{last}» y «{before}»
                            </span>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {rows.map((row) => (
                        <TableRow key={`${row.type}:${row.key}`}>
                            <TableCell className="pl-6 whitespace-normal">
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
                            <TableCell className="pr-6 text-right font-semibold tabular-nums">
                                {row.difference_text}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
                <TableFooter>
                    {[
                        [
                            'Costo del primer año',
                            totals.first_year,
                            totals.first_year_difference,
                        ] as const,
                        [
                            'Cada año después',
                            totals.recurring,
                            totals.recurring_difference,
                        ] as const,
                    ].map(([label, cells, difference]) => (
                        <TableRow key={label}>
                            <TableCell className="pl-6 font-semibold">
                                {label}
                            </TableCell>
                            {cells.map((cell, index) => (
                                <TableCell
                                    key={scenarios[index]?.id ?? index}
                                    className="text-right font-semibold tabular-nums"
                                >
                                    {cell}
                                </TableCell>
                            ))}
                            <TableCell className="pr-6 text-right font-semibold tabular-nums">
                                {difference}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableFooter>
            </Table>
        </section>
    );
}
