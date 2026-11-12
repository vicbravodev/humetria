import { Head, setLayoutProps } from '@inertiajs/react';
import { Lock, Scale, Users } from 'lucide-react';
import CommentList from '@/components/engagement/results/comment-list';
import ManagerItems from '@/components/engagement/results/manager-items';
import ManagerNav from '@/components/engagement/results/manager-nav';
import SummaryThemes from '@/components/engagement/results/summary-themes';
import PageHeader from '@/components/humetria/page-header';
import {
    useEngagementRoutes,
    useEngagementTrail,
    useSurveyCrumb,
} from '@/hooks/use-engagement-routes';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { managerReading } from '@/lib/engagement/manager';
import type {
    ManagerComment,
    ManagerFeedback,
    ManagerResults,
    ManagerRow,
    Paginated,
} from '@/types';

/**
 * The feedback of one manager to prepare the conversation (pantalla Clima · Jefe): one sentence, the questions as a
 * dumbbell against the company, the summary and, for who manages climate, the comments of the group. Never a ranking.
 */
export default function Manager({
    survey,
    manager,
    feedback,
    neighbors,
    commentsState,
    comments,
}: Pick<ManagerResults, 'survey'> & {
    manager: ManagerRow;
    feedback: ManagerFeedback;
    neighbors: { previous: ManagerRow | null; next: ManagerRow | null };
    /** 'hidden': this role reads only the summary; 'few': not enough comments to show them. */
    commentsState: 'hidden' | 'few' | 'shown';
    comments: Paginated<ManagerComment> | null;
}) {
    const tenantSlug = useTenantSlug();
    const routes = useEngagementRoutes();
    const reading = managerReading(feedback.items);
    setLayoutProps({
        breadcrumbs: useEngagementTrail(
            useSurveyCrumb(survey),
            {
                title: 'Por jefe',
                href: routes.managers.url([tenantSlug, survey.id]),
            },
            { title: manager.name },
        ),
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
                            icon: Users,
                            text: feedback.line
                                ? 'Resultado de su equipo completo'
                                : 'Resultado de quienes le reportan directamente',
                        },
                        { icon: Lock, text: 'Grupos de 5 o más' },
                        { icon: Scale, text: 'Aquí no hay ranking' },
                    ]}
                >
                    <ManagerNav surveyId={survey.id} {...neighbors} />
                </PageHeader>
                {reading !== null && (
                    <h2 className="max-w-[760px] text-xl leading-7 font-semibold text-pretty">
                        {reading}
                    </h2>
                )}
                <ManagerItems items={feedback.items} />
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
                    <p className="flex items-center gap-1.5 text-muted-foreground">
                        <Lock aria-hidden className="size-4 shrink-0" />
                        Hay menos de 10 comentarios: no se muestran para
                        proteger a quien escribió.
                    </p>
                )}
            </div>
        </>
    );
}
