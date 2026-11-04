import RiskLevel from '@/components/humetria/risk-level';
import RowMenu from '@/components/interventions/row-menu';
import { Badge } from '@/components/ui/badge';
import { TableCell, TableRow } from '@/components/ui/table';
import { meterText, SCOPE_LABELS, stateVariant } from '@/lib/interventions';
import type { ProgramRow, ProgramTools } from '@/types';

/** One row of the Programa (pantalla Empleados, a table row): what it attends, its level, its actions and its analysis. */
export default function RowLine({
    row,
    campaignId,
    tools,
}: {
    row: ProgramRow;
    campaignId: string;
    tools: ProgramTools | null;
}) {
    return (
        <TableRow>
            <TableCell className="max-w-xs whitespace-normal">
                <p className="font-medium">{row.label}</p>
                <p className="text-sm text-muted-foreground">
                    {SCOPE_LABELS[row.scope]}
                </p>
                {row.analysis !== null && (
                    <p className="mt-1 line-clamp-2 text-sm">{row.analysis}</p>
                )}
            </TableCell>
            <TableCell>
                {row.level === null ? (
                    <span className="text-sm text-muted-foreground">
                        Agregada por RH
                    </span>
                ) : (
                    <RiskLevel
                        level={row.level.value}
                        label={row.level.label}
                    />
                )}
            </TableCell>
            <TableCell className="whitespace-normal">
                <Badge variant={stateVariant(row.state.value)}>
                    {row.state.label}
                </Badge>
                <p className="mt-1 text-sm text-muted-foreground">
                    {meterText(row.meter)}
                </p>
            </TableCell>
            <TableCell>
                {row.missingAnalysis && (
                    <Badge variant="warning">Falta el análisis</Badge>
                )}
            </TableCell>
            <TableCell className="text-right">
                {tools !== null && (
                    <RowMenu row={row} campaignId={campaignId} tools={tools} />
                )}
            </TableCell>
        </TableRow>
    );
}
