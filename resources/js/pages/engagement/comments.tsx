import { Head, setLayoutProps } from '@inertiajs/react';
import { Hourglass } from 'lucide-react';
import CommentList from '@/components/engagement/results/comment-list';
import CommentTabs from '@/components/engagement/results/comment-tabs';
import RetrySummaryButton from '@/components/engagement/results/retry-summary-button';
import SummaryThemes from '@/components/engagement/results/summary-themes';
import EmptyState from '@/components/humetria/empty-state';
import Notice from '@/components/humetria/notice';
import PageHeader from '@/components/humetria/page-header';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { results } from '@/routes/tenant/climate';
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
        breadcrumbs: [
            { title: 'Clima' },
            { title: survey.name, href: results.url([tenantSlug, survey.id]) },
            { title: 'Comentarios' },
        ],
    });

    return (
        <>
            <Head title={`Comentarios · ${survey.name}`} />
            <div className="flex w-full flex-col gap-6 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    caption="Resultados de clima"
                    title="Comentarios sobre la empresa"
                    description="Lo que escribieron las personas, sin quién lo escribió. Solo se muestran con al menos 10 comentarios."
                />
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
                            <p className="text-muted-foreground">
                                No hay suficientes comentarios para mostrarlos.
                            </p>
                        )}
                    </>
                )}
            </div>
        </>
    );
}
