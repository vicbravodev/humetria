import { Lock } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import ResultsNav from '@/components/engagement/results/results-nav';
import type { ResultsView } from '@/components/engagement/results/results-nav';
import PageHeader from '@/components/humetria/page-header';
import { Badge } from '@/components/ui/badge';

/**
 * The top of a view of the results other than the summary (Por pregunta, Comentarios, Por jefe): the survey with its
 * state, what protects the figures, and the tabs of the views.
 */
export default function ResultsSubheader({
    survey,
    view,
    privacy = { icon: Lock, text: 'Grupos de 5 o más' },
}: {
    survey: { id: string; name: string; status: string };
    view: ResultsView;
    privacy?: { icon: LucideIcon; text: string };
}) {
    return (
        <>
            <PageHeader
                title={survey.name}
                status={
                    <Badge
                        variant={
                            survey.status === 'closed' ? 'secondary' : 'default'
                        }
                    >
                        {survey.status === 'closed' ? 'Cerrada' : 'En curso'}
                    </Badge>
                }
                facts={[privacy]}
            />
            {survey.status === 'closed' && (
                <ResultsNav surveyId={survey.id} active={view} />
            )}
        </>
    );
}
