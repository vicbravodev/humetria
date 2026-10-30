import { TableCell, TableRow } from '@/components/ui/table';
import { percentText } from '@/lib/results';
import type { DistributionRow } from '@/types';

/** One group's questionnaires by level: the count and its share of the group. */
export default function DistributionTableRow({
    row,
    withAnswered,
}: {
    row: DistributionRow;
    withAnswered: boolean;
}) {
    const total = row.levels.reduce((sum, count) => sum + count, 0);

    return (
        <TableRow>
            <TableCell className="font-medium whitespace-normal">
                {row.label}
            </TableCell>
            {withAnswered && (
                <TableCell className="text-right tabular-nums">
                    {row.answered}
                </TableCell>
            )}
            {row.levels.map((count, index) => (
                <TableCell key={index} className="text-right tabular-nums">
                    {count}
                    <span className="block text-sm text-muted-foreground">
                        {percentText(count, total)}
                    </span>
                </TableCell>
            ))}
        </TableRow>
    );
}
