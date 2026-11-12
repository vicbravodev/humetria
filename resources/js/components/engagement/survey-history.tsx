import SurveyHistoryRow from '@/components/engagement/survey-history-row';
import Pagination from '@/components/humetria/pagination';
import StackedTable from '@/components/humetria/stacked-table';
import {
    TableBody,
    TableCaption,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    useEngagementKind,
    useEngagementRoutes,
} from '@/hooks/use-engagement-routes';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { averageRate } from '@/lib/engagement/panel';
import type { ClimateSurveyHistoryRow, Paginated } from '@/types';

/**
 * «Historial»: the closed surveys, the latest first and a page at a time, each row a link to the survey, with its
 * participation against the average of the page.
 */
export default function SurveyHistory({
    surveys,
}: {
    surveys: Paginated<ClimateSurveyHistoryRow>;
}) {
    const tenantSlug = useTenantSlug();
    const routes = useEngagementRoutes();
    const withMode = useEngagementKind() === 'pulse';
    const average = averageRate(surveys.data);

    if (surveys.total === 0) {
        return null;
    }

    return (
        <section aria-labelledby="history" className="flex flex-col gap-3">
            <div className="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1">
                <h2 id="history" className="text-xl font-semibold">
                    Historial
                </h2>
                <p className="text-sm text-muted-foreground">
                    Del más reciente al más antiguo.
                    {average !== null &&
                        ` La marca vertical es el promedio de participación: ${average} %.`}
                </p>
            </div>
            <StackedTable>
                <TableCaption className="sr-only">
                    Encuestas cerradas
                </TableCaption>
                <TableHeader>
                    <TableRow>
                        <TableHead>Encuesta</TableHead>
                        {withMode && <TableHead>Modo</TableHead>}
                        <TableHead>Cerró</TableHead>
                        <TableHead>Participación</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {surveys.data.map((survey) => (
                        <SurveyHistoryRow
                            key={survey.id}
                            survey={survey}
                            href={routes.results.url([tenantSlug, survey.id])}
                            withMode={withMode}
                            average={average}
                        />
                    ))}
                </TableBody>
            </StackedTable>
            <Pagination
                previous="Ver más recientes"
                next="Ver más antiguas"
                page={surveys}
                label="Páginas del historial"
            />
        </section>
    );
}
