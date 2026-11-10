import { Link } from '@inertiajs/react';
import { ClipboardCheck, ListChecks } from 'lucide-react';
import PageHeader from '@/components/humetria/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { items } from '@/routes/tenant/climate/results';
import type { EngagementResults } from '@/types';

/** The top of the results (pantallas: Eval-Cerrada): the survey with its state, what the figures are and the way to each question. */
export default function ResultsHeader({
    tenantSlug,
    survey,
    available,
}: {
    tenantSlug: string;
    survey: EngagementResults['survey'];
    available: boolean;
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
        >
            {available && (
                <Button asChild variant="outline">
                    <Link href={items.url([tenantSlug, survey.id])}>
                        <ListChecks aria-hidden />
                        Ver por pregunta
                    </Link>
                </Button>
            )}
        </PageHeader>
    );
}
