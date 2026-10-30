import RiskLevel from '@/components/humetria/risk-level';
import {
    Table,
    TableBody,
    TableCaption,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import DistributionTableRow from '@/components/results/distribution-table-row';
import type { DistributionRow, RiskLevelInfo } from '@/types';

/**
 * How many questionnaires fall in each risk level, one row per group (the center, a category, a domain, an area).
 * Counted, never averaged (criterio C-14).
 */
export default function DistributionTable({
    caption,
    heading,
    rows,
    levels,
}: {
    caption: string;
    heading: string;
    rows: DistributionRow[];
    levels: RiskLevelInfo[];
}) {
    const withAnswered = rows.some((row) => row.answered !== undefined);

    return (
        <Table>
            <TableCaption className="sr-only">{caption}</TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>{heading}</TableHead>
                    {withAnswered && (
                        <TableHead className="text-right">
                            Cuestionarios
                        </TableHead>
                    )}
                    {levels.map((level) => (
                        <TableHead key={level.value} className="text-right">
                            <RiskLevel
                                level={level.value}
                                label={level.label}
                            />
                        </TableHead>
                    ))}
                </TableRow>
            </TableHeader>
            <TableBody>
                {rows.map((row) => (
                    <DistributionTableRow
                        key={row.key}
                        row={row}
                        withAnswered={withAnswered}
                    />
                ))}
            </TableBody>
        </Table>
    );
}
