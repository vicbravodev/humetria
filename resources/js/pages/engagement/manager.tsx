import { Head, setLayoutProps } from '@inertiajs/react';
import { ClipboardCheck, Users } from 'lucide-react';
import CommentList from '@/components/engagement/results/comment-list';
import HighlightsCard from '@/components/engagement/results/highlights-card';
import ManagerItems from '@/components/engagement/results/manager-items';
import SummaryThemes from '@/components/engagement/results/summary-themes';
import PageHeader from '@/components/humetria/page-header';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { managers, results } from '@/routes/tenant/climate';
import type {
    ManagerComment,
    ManagerFeedback,
    ManagerResults,
    ManagerRow,
    Paginated,
} from '@/types';

/** The feedback of one manager as they will read it, plus the original comments of the group for who manages climate. */
export default function Manager({
    survey,
    manager,
    feedback,
    commentsState,
    comments,
}: Pick<ManagerResults, 'survey'> & {
    manager: ManagerRow;
    feedback: ManagerFeedback;
    /** 'hidden': this role reads only the summary; 'few': not enough comments to show them. */
    commentsState: 'hidden' | 'few' | 'shown';
    comments: Paginated<ManagerComment> | null;
}) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: [
            { title: 'Clima' },
            { title: survey.name, href: results.url([tenantSlug, survey.id]) },
            {
                title: 'Por jefe',
                href: managers.url([tenantSlug, survey.id]),
            },
            { title: manager.name },
        ],
    });

    return (
        <>
            <Head title={`${manager.name} · Retroalimentación`} />
            <div className="flex w-full flex-col gap-6 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    caption="Retroalimentación del jefe"
                    title={manager.name}
                    facts={[
                        {
                            icon: ClipboardCheck,
                            text: 'Solo se muestran grupos de al menos 5 respuestas',
                        },
                        {
                            icon: Users,
                            text: feedback.line
                                ? 'Resultado de su equipo completo'
                                : 'Resultado de quienes le reportan directamente',
                        },
                    ]}
                />
                <ManagerItems items={feedback.items} />
                <HighlightsCard
                    strengths={feedback.strengths}
                    opportunities={feedback.opportunities}
                />
                {feedback.summary !== null && (
                    <SummaryThemes summary={feedback.summary} />
                )}
                {comments !== null && (
                    <CommentList
                        heading="Comentarios del grupo"
                        comments={comments}
                    />
                )}
                {commentsState === 'few' && (
                    <p className="text-muted-foreground">
                        No hay suficientes comentarios para mostrarlos.
                    </p>
                )}
            </div>
        </>
    );
}
