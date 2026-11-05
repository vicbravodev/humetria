import MeasureActions, { LINK } from '@/components/seating/measure-actions';
import MeasureCardMeta from '@/components/seating/measure-card-meta';
import type { MeasureRow } from '@/types';

/** One measure on the plan (board LS-Plan): what, for which position, who answers, by when, its evidence and its follow-up. */
export default function MeasureCard({
    workCenterId,
    measure,
    canManage,
}: {
    workCenterId: string;
    measure: MeasureRow;
    canManage: boolean;
}) {
    return (
        <li className="flex flex-col gap-2 card p-4">
            <p className="font-medium">{measure.kindLabel}</p>
            {measure.note !== null && (
                <p className="text-sm text-muted-foreground">{measure.note}</p>
            )}
            <p className="text-sm text-muted-foreground">
                {measure.fromAssessment
                    ? 'De una evaluación individual'
                    : (measure.positionName ?? 'Todo el centro')}
            </p>
            <MeasureCardMeta measure={measure} />
            {measure.evidenceHref !== null && (
                <a
                    href={measure.evidenceHref}
                    target="_blank"
                    rel="noreferrer"
                    className={`self-start text-sm font-medium ${LINK}`}
                >
                    Evidencia
                </a>
            )}
            {canManage && measure.status !== 'done' && (
                <div className="text-sm">
                    <MeasureActions
                        workCenterId={workCenterId}
                        measure={measure}
                    />
                </div>
            )}
        </li>
    );
}
