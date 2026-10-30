import { Link } from '@inertiajs/react';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { guidesText, staffText } from '@/lib/work-centers';
import { show } from '@/routes/tenant/campaigns';
import type { WorkCenterEvaluation } from '@/types';

/** Each active work center: its people, the guides that apply, its last evaluation and when the next is due (7.9). */
export default function WorkCenterEvaluationsTable({
    tenantSlug,
    workCenters,
}: {
    tenantSlug: string;
    workCenters: WorkCenterEvaluation[];
}) {
    return (
        <Table>
            <TableCaption className="sr-only">
                Centros de trabajo y su última evaluación NOM-035
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Centro de trabajo</TableHead>
                    <TableHead>Personal</TableHead>
                    <TableHead>Última evaluación</TableHead>
                    <TableHead>Siguiente antes del</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {workCenters.map((workCenter) => (
                    <TableRow key={workCenter.id}>
                        <TableCell className="whitespace-normal">
                            <p className="font-medium">{workCenter.name}</p>
                            {workCenter.openCampaignId !== null && (
                                <Link
                                    href={show([
                                        tenantSlug,
                                        workCenter.openCampaignId,
                                    ])}
                                    className="text-sm text-primary underline-offset-4 hover:underline"
                                >
                                    Evaluación abierta
                                </Link>
                            )}
                        </TableCell>
                        <TableCell className="text-sm whitespace-nowrap">
                            <p className="tabular-nums">
                                {staffText(workCenter.workers)}
                            </p>
                            <p className="text-muted-foreground">
                                {guidesText(workCenter.guides) ?? '—'}
                            </p>
                        </TableCell>
                        <TableCell className="text-sm">
                            {workCenter.lastEvaluatedOn ?? 'Sin evaluar'}
                        </TableCell>
                        <TableCell className="text-sm">
                            {workCenter.dueOn === null ? (
                                <span className="text-muted-foreground">—</span>
                            ) : (
                                <span
                                    className={
                                        workCenter.overdue
                                            ? 'font-medium text-destructive'
                                            : undefined
                                    }
                                >
                                    {workCenter.dueOn}
                                    {workCenter.overdue && ' · vencida'}
                                </span>
                            )}
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
