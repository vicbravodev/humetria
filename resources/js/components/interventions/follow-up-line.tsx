import { useState } from 'react';
import FollowUpDialog from '@/components/interventions/follow-up-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { TableCell, TableRow } from '@/components/ui/table';
import type { ProgramRow, ProgramTools } from '@/types';

/** The evaluation after the measures of one row (num. 8.4 e): when it is planned, when it was done and what it found. */
export default function FollowUpLine({
    row,
    campaignId,
    tools,
}: {
    row: ProgramRow;
    campaignId: string;
    tools: ProgramTools | null;
}) {
    const [open, setOpen] = useState(false);
    const { followUp } = row;
    const done = followUp.doneOn !== null;

    return (
        <TableRow>
            <TableCell className="font-medium whitespace-normal">
                {row.label}
            </TableCell>
            <TableCell>{followUp.dueOnLabel ?? '—'}</TableCell>
            <TableCell>
                {done ? (
                    <Badge variant="success">{followUp.doneOnLabel}</Badge>
                ) : (
                    '—'
                )}
            </TableCell>
            <TableCell className="max-w-xs whitespace-normal">
                {followUp.result ?? '—'}
                {followUp.evaluation !== null && (
                    <p className="text-sm text-muted-foreground">
                        {followUp.evaluation.label}
                    </p>
                )}
            </TableCell>
            <TableCell className="text-right">
                {tools !== null && (
                    <>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setOpen(true)}
                        >
                            {done ? 'Editar' : 'Programar o registrar'}
                        </Button>
                        {open && (
                            <FollowUpDialog
                                open={open}
                                onOpenChange={setOpen}
                                campaignId={campaignId}
                                row={row}
                                suggestion={tools.suggestion}
                                evaluations={tools.evaluations}
                            />
                        )}
                    </>
                )}
            </TableCell>
        </TableRow>
    );
}
