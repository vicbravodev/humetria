import { Link } from '@inertiajs/react';
import NewEvaluationDialog from '@/components/campaigns/new-evaluation-dialog';
import { Button } from '@/components/ui/button';
import { centerFollowUp } from '@/lib/evaluation-centers';
import { results, show } from '@/routes/tenant/campaigns';
import type { WorkCenterEvaluation } from '@/types';

/**
 * A work center's card when no evaluation runs (pantalla NOM035): its last evaluation, when the next is due, and the
 * way to its results, its draft or a new evaluation.
 */
export default function CenterFollowUp({
    tenantSlug,
    center,
    canManage,
    minGroup,
}: {
    tenantSlug: string;
    center: WorkCenterEvaluation;
    canManage: boolean;
    minGroup: number;
}) {
    const draft = center.openCampaign;

    return (
        <div className="flex flex-wrap items-center gap-3">
            <div className="flex min-w-0 grow flex-col gap-0.5">
                <p className="text-sm">
                    {center.lastEvaluatedOn === null ? (
                        'Sin evaluaciones todavía'
                    ) : (
                        <>
                            Última evaluación{' '}
                            <span className="font-semibold">
                                {center.lastEvaluatedOn}
                            </span>
                        </>
                    )}
                </p>
                <p className="text-[13px] leading-[18px] text-muted-foreground">
                    {centerFollowUp(center)}
                </p>
            </div>
            <div className="flex flex-wrap gap-2">
                {center.lastCampaignId !== null && (
                    <Button size="sm" variant="outline" asChild>
                        <Link
                            href={results([tenantSlug, center.lastCampaignId])}
                        >
                            Ver resultados
                            <span className="sr-only"> de {center.name}</span>
                        </Link>
                    </Button>
                )}
                {draft !== null && (
                    <Button size="sm" asChild>
                        <Link href={show([tenantSlug, draft.id])}>
                            {canManage ? 'Continuar borrador' : 'Ver borrador'}
                            <span className="sr-only"> de {center.name}</span>
                        </Link>
                    </Button>
                )}
                {canManage && center.eligible && (
                    <NewEvaluationDialog
                        tenantSlug={tenantSlug}
                        workCenter={center}
                        minGroup={minGroup}
                    >
                        <Button size="sm">
                            Evaluar
                            <span className="sr-only"> {center.name}</span>
                        </Button>
                    </NewEvaluationDialog>
                )}
            </div>
        </div>
    );
}
