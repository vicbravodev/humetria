import { Link } from '@inertiajs/react';
import { ClipboardCheck, Download, ListChecks } from 'lucide-react';
import PageHeader from '@/components/humetria/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { EngagementResults } from '@/types';
import {
    useEngagementName,
    useEngagementRoutes,
} from '@/hooks/use-engagement-routes';

/** The top of the results (pantallas: Eval-Cerrada): the survey with its state, what the figures are and the way to each question. */
export default function ResultsHeader({
    tenantSlug,
    survey,
    available,
    cut,
}: {
    tenantSlug: string;
    survey: EngagementResults['survey'];
    available: boolean;
    cut: EngagementResults['cut'];
}) {
    const routes = useEngagementRoutes();
    const name = useEngagementName();

    return (
        <PageHeader
            caption={`Resultados de ${name.toLowerCase()}`}
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
                <>
                    <Button asChild variant="outline">
                        <a
                            href={routes.export.url(
                                [tenantSlug, survey.id],
                                cut === null ? {} : { query: { cut } },
                            )}
                            download
                        >
                            <Download aria-hidden />
                            {cut === null
                                ? 'Exportar CSV'
                                : 'Exportar este corte'}
                        </a>
                    </Button>
                    <Button asChild variant="outline">
                        <Link
                            href={routes.results.items.url([
                                tenantSlug,
                                survey.id,
                            ])}
                        >
                            <ListChecks aria-hidden />
                            Ver por pregunta
                        </Link>
                    </Button>
                </>
            )}
        </PageHeader>
    );
}
