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

/**
 * Scenarios side by side in four fixed columns that also hold on paper: the raise, what the first year costs (the figure and a thin
 * bar under it), how much the payroll really grows and a person a month. The proposal in question has a bar at its side and is bold,
 * so it stands out in black and white too.
 */
export default function ReportScenarioTable({
    caption,
    rows,
}: {
    caption: string;
    rows: ScenarioRow[];
}) {
    return (
        <StackedTable className="table-fixed">
            <caption className="sr-only">{caption}</caption>
            <TableHeader>
                <TableRow>
                    <TableHead className="w-[20%] whitespace-nowrap">
                        Escenario
                    </TableHead>
                    <TableHead className="w-[38%] whitespace-nowrap">
                        Primer año
                    </TableHead>
                    <TableHead className="w-[18%] text-right whitespace-nowrap">
                        Nómina sube
                    </TableHead>
                    <TableHead className="w-[24%] text-right whitespace-nowrap">
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
                        <TableCell
                            className={cn(
                                'font-medium whitespace-normal max-md:w-full',
                                row.current &&
                                    'border-l-4 border-l-foreground font-bold',
                            )}
                        >
                            {row.name}
                            {!row.name.startsWith(row.raiseText) && (
                                <span className="block text-sm font-normal text-muted-foreground">
                                    {row.raiseText}
                                </span>
                            )}
                        </TableCell>
                        <TableCell className="max-md:w-full">
                            <span className="flex flex-col gap-1.5">
                                <span className="font-semibold tabular-nums">
                                    {row.costText}
                                </span>
                                <BarMeter
                                    percent={row.percent}
                                    tooltip={`${row.name}: ${row.costText}`}
                                    muted={!row.current}
                                    index={index}
                                />
                            </span>
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
