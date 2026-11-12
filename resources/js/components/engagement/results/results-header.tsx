import { Link } from '@inertiajs/react';
import { CalendarCheck, Download, Lock, Users } from 'lucide-react';
import PageHeader from '@/components/humetria/page-header';
import RowMenu from '@/components/humetria/row-menu';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useEngagementRoutes } from '@/hooks/use-engagement-routes';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { closedText, participationText } from '@/lib/engagement/results';
import type { EngagementResults, PageFact } from '@/types';

/**
 * The top of the results (pantalla Clima · Resultados): the survey with its state, a fact sheet (when it closed, how
 * many answered, the minimum of a group), «Exportar CSV» under «Más» and, as the next step of the cycle, writing the
 * commitments or seeing them.
 */
export default function ResultsHeader({
    results,
}: {
    results: EngagementResults;
}) {
    const tenantSlug = useTenantSlug();
    const routes = useEngagementRoutes();
    const { survey, participation, cut } = results;
    const at: [string, string] = [tenantSlug, survey.id];
    const closed = closedText(survey.closedOn);
    const facts: PageFact[] = [
        ...(closed === null ? [] : [{ icon: CalendarCheck, text: closed }]),
        ...(participation === null
            ? []
            : [{ icon: Users, text: participationText(participation) }]),
        { icon: Lock, text: 'Grupos de 5 o más' },
    ];

    return (
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
            facts={facts}
        >
            {results.available && (
                <>
                    <RowMenu
                        label={`Más acciones de ${survey.name}`}
                        items={[
                            {
                                label:
                                    cut === null
                                        ? 'Exportar CSV'
                                        : 'Exportar este corte',
                                icon: Download,
                                href: routes.export.url(
                                    at,
                                    cut === null ? {} : { query: { cut } },
                                ),
                            },
                        ]}
                    />
                    <Button asChild>
                        <Link href={routes.commitments.index.url(at)}>
                            {results.commitments === 0
                                ? 'Escribir compromisos'
                                : 'Ver compromisos'}
                        </Link>
                    </Button>
                </>
            )}
        </PageHeader>
    );
}
