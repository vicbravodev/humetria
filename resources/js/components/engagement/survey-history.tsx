import { Link } from '@inertiajs/react';
import Pagination from '@/components/humetria/pagination';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { describeDate } from '@/lib/calendar-date';
import { show } from '@/routes/tenant/climate';
import type { ClimateSurveyHistoryRow, Paginated } from '@/types';

/** "Historial" (pantalla NOM035): the closed surveys, a page at a time, with how many answered each. */
export default function SurveyHistory({
    surveys,
}: {
    surveys: Paginated<ClimateSurveyHistoryRow>;
}) {
    const tenantSlug = useTenantSlug();

    if (surveys.total === 0) {
        return null;
    }

    return (
        <section aria-labelledby="history" className="flex flex-col gap-3.5">
            <h2 id="history" className="text-lg leading-[26px] font-semibold">
                Historial
            </h2>
            <Table>
                <TableCaption className="sr-only">
                    Encuestas cerradas
                </TableCaption>
                <TableHeader>
                    <TableRow>
                        <TableHead>Encuesta</TableHead>
                        <TableHead>Cerró</TableHead>
                        <TableHead className="text-right">
                            Contestaron
                        </TableHead>
                        <TableHead>
                            <span className="sr-only">Ver</span>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {surveys.data.map((survey) => (
                        <TableRow key={survey.id}>
                            <TableCell className="font-medium">
                                {survey.name}
                            </TableCell>
                            <TableCell>
                                {survey.closedOn === null
                                    ? '—'
                                    : (describeDate(survey.closedOn) ??
                                      survey.closedOn)}
                            </TableCell>
                            <TableCell className="text-right tabular-nums">
                                {survey.invited === null
                                    ? '—'
                                    : `${survey.answered} de ${survey.invited}`}
                                {survey.rate !== null && ` · ${survey.rate} %`}
                            </TableCell>
                            <TableCell className="text-right">
                                <Link
                                    href={show([tenantSlug, survey.id])}
                                    className="touch-hit text-sm font-medium text-primary underline-offset-4 hover:underline"
                                >
                                    Ver
                                    <span className="sr-only">
                                        {' '}
                                        {survey.name}
                                    </span>
                                </Link>
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
            <Pagination
                previous="Ver más recientes"
                next="Ver más antiguas"
                page={surveys}
                label="Páginas del historial"
            />
        </section>
    );
}
