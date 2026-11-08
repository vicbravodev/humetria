import { CalendarX2 } from 'lucide-react';
import ReportPersonRows from '@/components/attendance/report-person-rows';
import EmptyState from '@/components/humetria/empty-state';
import Pagination from '@/components/humetria/pagination';
import SearchField from '@/components/humetria/search-field';
import {
    Table,
    TableBody,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { reportQuery } from '@/lib/attendance/report';
import { report } from '@/routes/tenant/attendance';
import type { Paginated, ReportPerson } from '@/types';

/** The people of the period by name, 25 at a time, each with their days; searched by name. */
export default function ReportPeople({
    people,
    filters,
}: {
    people: Paginated<ReportPerson>;
    filters: { center: string; from: string; to: string; q: string };
}) {
    const tenantSlug = useTenantSlug();
    const { text, type } = useQueryFilters(
        report.url(tenantSlug),
        filters,
        reportQuery,
    );

    return (
        <section aria-labelledby="dias" className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center justify-between gap-4">
                <h2 id="dias" className="text-xl leading-7 font-semibold">
                    Día por día
                </h2>
                <SearchField
                    label="Buscar por nombre"
                    value={text}
                    onChange={type}
                    className="w-full sm:max-w-[320px]"
                />
            </div>
            {people.total === 0 ? (
                <EmptyState
                    icon={CalendarX2}
                    title={
                        filters.q === ''
                            ? 'Ningún día en este periodo'
                            : 'Nadie con ese nombre'
                    }
                >
                    {filters.q === ''
                        ? 'Nadie marcó en este centro en esas fechas. Elige otro periodo o revisa el centro arriba.'
                        : 'Revisa cómo lo escribiste o busca con otra parte del nombre.'}
                </EmptyState>
            ) : (
                <>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Día</TableHead>
                                <TableHead>Inicio – fin</TableHead>
                                <TableHead>Pausas</TableHead>
                                <TableHead>Horas</TableHead>
                                <TableHead>Evidencia</TableHead>
                                <TableHead>Correcciones y avisos</TableHead>
                                <TableHead>Semana</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {people.data.map((person) => (
                                <ReportPersonRows
                                    key={person.id}
                                    person={person}
                                />
                            ))}
                        </TableBody>
                    </Table>
                    <Pagination
                        page={people}
                        label="Páginas de personas"
                        pinned={false}
                    />
                </>
            )}
        </section>
    );
}
