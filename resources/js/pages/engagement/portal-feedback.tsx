import { Head } from '@inertiajs/react';
import { ClipboardCheck, Users } from 'lucide-react';
import HighlightsCard from '@/components/engagement/results/highlights-card';
import ManagerItems from '@/components/engagement/results/manager-items';
import SummaryThemes from '@/components/engagement/results/summary-themes';
import FeedbackEmpty from '@/components/engagement/portal/feedback-empty';
import PortalShell from '@/components/engagement/portal/portal-shell';
import PageHeader from '@/components/humetria/page-header';
import type { ManagerFeedback } from '@/types';

/**
 * What the manager's group said about them, in usted: each question against the company, the best and the worst, and the
 * summary of the comments. Group figures only: never an answer or a comment, and not how many people there are.
 */
export default function PortalFeedback({
    company,
    survey,
    feedback,
}: {
    token: string;
    company: string;
    survey: { name: string } | null;
    feedback: ManagerFeedback | null;
}) {
    return (
        <PortalShell company={company}>
            <Head title="Retroalimentación de su equipo" />
            <div className="flex w-full max-w-3xl flex-col gap-6">
                {survey === null || feedback === null ? (
                    <FeedbackEmpty />
                ) : (
                    <>
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
                        />
                        {feedback.summary !== null && (
                            <SummaryThemes summary={feedback.summary} />
                        )}
                    </>
                )}
            </div>
        </PortalShell>
    );
}
