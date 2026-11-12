import { Head, setLayoutProps } from '@inertiajs/react';
import { Hourglass, Lock } from 'lucide-react';
import CommentList from '@/components/engagement/results/comment-list';
import CommentTabs from '@/components/engagement/results/comment-tabs';
import RetrySummaryButton from '@/components/engagement/results/retry-summary-button';
import SummaryThemes from '@/components/engagement/results/summary-themes';
import EmptyState from '@/components/humetria/empty-state';
import Notice from '@/components/humetria/notice';
import ResultsSubheader from '@/components/engagement/results/results-subheader';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import {
    useEngagementTrail,
    useSurveyCrumb,
} from '@/hooks/use-engagement-routes';
import type {
    CommentSummary,
    ManagerComment,
    ManagerResults,
    Paginated,
} from '@/types';

/**
 * The comments about the company (pantallas: Eval-Cerrada): the summary made with AI when the survey closed and, for who
 * manages climate, the original comments of each open question once there are enough. Under the minimum, no text and
 * no count.
 */
export default function Comments({
    survey,
    available,
    item,
    sensitiveMentions,
    summary,
    retryId,
    commentsState,
    comments,
}: Pick<ManagerResults, 'survey' | 'available'> & {
    item: string;
    sensitiveMentions: boolean;
    summary: CommentSummary | null;
    /** Set when the summary failed and this person may try it again. */
    retryId: string | null;
    commentsState: 'hidden' | 'few' | 'shown';
    comments: Paginated<ManagerComment> | null;
}) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: useEngagementTrail(useSurveyCrumb(survey), {
            title: 'Comentarios',
        }),
    });

    return (
        <>
            <Head title={`Comentarios · ${survey.name}`} />
            <div className="flex w-full flex-col gap-6 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <ResultsSubheader
                    survey={survey}
                    view="comments"
                    privacy={{
                        icon: Lock,
                        text: 'Sin quién lo escribió · se muestran con 10 o más',
                    }}
                />
                <h2 className="text-xl font-semibold">
                    Comentarios sobre la empresa
                </h2>
                {sensitiveMentions && (
                    <Notice>
                        <strong>
                            Hubo menciones de posible acoso o violencia.
                        </strong>{' '}
                        Te recomendamos difundir el canal de quejas entre tus
                        personas.
                    </Notice>
                )}
                {!available ? (
                    <EmptyState
                        icon={Hourglass}
                        title="Los comentarios estarán disponibles al cerrar la encuesta"
                    >
                        Mientras sigue abierta, cada comentario nuevo diría qué
                        escribió quien acaba de contestar.
                    </EmptyState>
                ) : (
                    <>
                        {summary !== null && (
                            <SummaryThemes
                                summary={summary}
                                action={
                                    retryId === null ? undefined : (
                                        <RetrySummaryButton
                                            summaryId={retryId}
                                        />
                                    )
                                }
                            />
                        )}
                        {commentsState !== 'hidden' && (
                            <CommentTabs
                                tenantSlug={tenantSlug}
                                surveyId={survey.id}
                                selected={item}
                            />
                        )}
                        {comments !== null && (
                            <CommentList
                                heading="Comentarios"
                                comments={comments}
                            />
                        )}
                        {commentsState === 'few' && (
                            <p className="flex items-center gap-1.5 text-muted-foreground">
                                <Lock aria-hidden className="size-4 shrink-0" />
                                Hay menos de 10 comentarios: no se muestran para
                                proteger a quien escribió.
                            </p>
                        )}
                    </>
                )}
            </div>
        </>
    );
}
