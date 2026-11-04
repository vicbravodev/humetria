import ActionMenu from '@/components/interventions/action-menu';
import { Badge } from '@/components/ui/badge';
import { TableCell, TableRow } from '@/components/ui/table';
import { statusVariant, subjectsText } from '@/lib/interventions';
import type { ProgramAction, ProgramRow, ProgramTools } from '@/types';

/** One action of the Programa: what, in which row, for whom, by when, who answers and where it stands. */
export default function ActionLine({
    action,
    campaignId,
    rows,
    tools,
}: {
    action: ProgramAction;
    campaignId: string;
    rows: ProgramRow[];
    tools: ProgramTools | null;
}) {
    return (
        <TableRow>
            <TableCell className="max-w-xs whitespace-normal">
                <p className="font-medium">{action.title}</p>
                <p className="text-sm text-muted-foreground">
                    {action.kind.label} · {action.level.label}
                </p>
            </TableCell>
            <TableCell className="whitespace-normal">
                {action.itemLabel}
            </TableCell>
            <TableCell className="whitespace-normal">
                {subjectsText(action)}
                {action.professional !== null && (
                    <p className="text-sm text-muted-foreground">
                        {action.professional.label}
                    </p>
                )}
            </TableCell>
            <TableCell className="whitespace-normal">
                {action.dueOnLabel}
                {action.overdue && (
                    <Badge variant="destructive" className="ml-2">
                        Vencida
                    </Badge>
                )}
                {action.completedOnLabel !== null && (
                    <p className="text-sm text-muted-foreground">
                        Hecha el {action.completedOnLabel}
                    </p>
                )}
            </TableCell>
            <TableCell className="whitespace-normal">
                {action.responsible.name}
            </TableCell>
            <TableCell>
                <Badge variant={statusVariant(action.status.value)}>
                    {action.status.label}
                </Badge>
            </TableCell>
            <TableCell className="text-right">
                <ActionMenu
                    action={action}
                    campaignId={campaignId}
                    rows={rows}
                    tools={tools}
                />
            </TableCell>
        </TableRow>
    );
}
