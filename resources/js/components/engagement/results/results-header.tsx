import { ClipboardCheck } from 'lucide-react';
import PageHeader from '@/components/humetria/page-header';
import { Badge } from '@/components/ui/badge';
import type { EngagementResults } from '@/types';

/** The top of the results (pantallas: Eval-Cerrada): the survey with its state and what the figures are. */
export default function ResultsHeader({
    survey,
}: {
    survey: EngagementResults['survey'];
}) {
    return (
        <PageHeader
            caption="Resultados de clima"
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
            facts={[
                {
                    icon: ClipboardCheck,
                    text: 'Solo se muestran grupos de al menos 5 respuestas',
                },
            ]}
        />
    );
}
