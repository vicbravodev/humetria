import { Head } from '@inertiajs/react';
import { ClipboardCheck, Hourglass, Users } from 'lucide-react';
import HighlightsCard from '@/components/engagement/results/highlights-card';
import ManagerItems from '@/components/engagement/results/manager-items';
import SummaryThemes from '@/components/engagement/results/summary-themes';
import PortalShell from '@/components/engagement/portal/portal-shell';
import PageHeader from '@/components/humetria/page-header';
import WorkerHomeLink from '@/components/worker-home-link';
import StateMessage from '@/components/worker-access/state-message';
import WorkerFrame from '@/components/worker-access/worker-frame';
import { home } from '@/routes/worker';
import type { ManagerFeedback } from '@/types';

/**
 * What the manager's group said about them, in usted: each question against the company, the best and the worst, and the
 * summary of the comments. Group figures only: never an answer or a comment, and not how many people there are.
 */
export default function PortalFeedback({
    slug,
    company,
    survey,
    feedback,
}: {
    slug: string;
    company: string;
    survey: { name: string } | null;
    /** With the minimum per group the survey froze. */
    feedback: (ManagerFeedback & { minGroup: number }) | null;
}) {
    if (survey === null || feedback === null) {
        // The same screen whether the survey is still open, the group was too small or the person has no team: it never says which.
        return (
            <WorkerFrame company={company} centered back={home.url(slug)}>
                <Head title="Retroalimentación de su equipo" />
                <StateMessage
                    icon={Hourglass}
                    title="Todavía no hay resultados para mostrar"
                >
                    Cuando haya resultados de su equipo los verá aquí, en este
                    mismo enlace.
                </StateMessage>
            </WorkerFrame>
        );
    }

    return (
        <PortalShell company={company}>
            <Head title="Retroalimentación de su equipo" />
            <div className="flex w-full max-w-3xl flex-col gap-6">
                <WorkerHomeLink slug={slug} />
                <PageHeader
                    caption={survey.name}
                    title="Lo que dijo su equipo"
                    facts={[
                        {
                            icon: ClipboardCheck,
                            text: 'Solo se muestran resultados de grupo',
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
                    minGroup={feedback.minGroup}
                />
                {feedback.summary !== null && (
                    <SummaryThemes summary={feedback.summary} />
                )}
            </div>
        </PortalShell>
    );
}
