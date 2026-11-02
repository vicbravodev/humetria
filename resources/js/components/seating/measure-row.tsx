import MeasureActions, { LINK } from '@/components/seating/measure-actions';
import MeasureWhat from '@/components/seating/measure-what';
import { Badge } from '@/components/ui/badge';
import { TableCell, TableRow } from '@/components/ui/table';
import type { MeasureRow as Measure } from '@/types';

/** One measure of the plan: what, for whom, who answers, by when, its state and what RH can do with it. */
export default function MeasureRow({
    workCenterId,
    measure,
    canManage,
}: {
    workCenterId: string;
    measure: Measure;
    canManage: boolean;
}) {
    const open = measure.status !== 'cancelled';

    return (
        <TableRow>
            <MeasureWhat measure={measure} />
            <TableCell>{measure.ownerName ?? '—'}</TableCell>
            <TableCell>
                {measure.dueOn ?? '—'}
                {measure.overdue && (
                    <span className="ml-2 text-caption font-medium text-destructive">
                        Vencida
                    </span>
                )}
            </TableCell>
            <TableCell>
                <Badge
                    variant={
                        measure.status === 'done' ? 'secondary' : 'outline'
                    }
                >
                    {measure.statusLabel}
                </Badge>
            </TableCell>
            <TableCell className="text-right whitespace-nowrap">
                {measure.evidenceHref !== null && (
                    <a
                        href={measure.evidenceHref}
                        target="_blank"
                        rel="noreferrer"
                        className={`mr-3 ${LINK}`}
                    >
                        Evidencia
                    </a>
                )}
                {canManage && open && (
                    <MeasureActions
                        workCenterId={workCenterId}
                        measure={measure}
                    />
                )}
            </TableCell>
        </TableRow>
    );
}
