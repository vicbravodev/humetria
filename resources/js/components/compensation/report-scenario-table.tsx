import BarMeter from '@/components/humetria/bar-meter';
import StackedTable from '@/components/humetria/stacked-table';
import {
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { ScenarioRow } from '@/lib/compensation/report-scenarios';
import { cn } from '@/lib/utils';

/** Scenarios side by side: the raise, a bar for what the first year costs, how much the payroll really grows and a person a month. */
export default function ReportScenarioTable({
    caption,
    rows,
}: {
    caption: string;
    rows: ScenarioRow[];
}) {
    return (
        <StackedTable>
            <caption className="sr-only">{caption}</caption>
            <TableHeader>
                <TableRow>
                    <TableHead>Escenario</TableHead>
                    <TableHead className="w-[28%]">Primer año</TableHead>
                    <TableHead className="text-right">Costo</TableHead>
                    <TableHead className="text-right">Nómina sube</TableHead>
                    <TableHead className="text-right">
                        Por persona al mes
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {rows.map((row, index) => (
                    <TableRow
                        key={row.key}
                        className={cn(row.current && 'bg-accent/40')}
                    >
                        <TableCell className="font-medium whitespace-normal max-md:w-full">
                            {row.name}
                            {!row.name.startsWith(row.raiseText) && (
                                <span className="block text-sm font-normal text-muted-foreground">
                                    {row.raiseText}
                                </span>
                            )}
                        </TableCell>
                        <TableCell className="max-md:w-full">
                            <BarMeter
                                percent={row.percent}
                                tooltip={`${row.name}: ${row.costText}`}
                                muted={!row.current}
                                index={index}
                            />
                        </TableCell>
                        <TableCell className="text-right font-semibold tabular-nums">
                            {row.costText}
                        </TableCell>
                        <TableCell className="text-right tabular-nums">
                            {row.growthText}
                        </TableCell>
                        <TableCell className="text-right tabular-nums">
                            {row.perPersonText}
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </StackedTable>
    );
}
