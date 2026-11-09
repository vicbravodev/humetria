import { CalendarX2 } from 'lucide-react';
import { Link } from '@inertiajs/react';
import ReportPersonRows from '@/components/attendance/report-person-rows';
import EmptyState from '@/components/humetria/empty-state';
import Pagination from '@/components/humetria/pagination';
import StackedTable from '@/components/humetria/stacked-table';
import { Button } from '@/components/ui/button';
import {
    TableBody,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { Paginated, ReportPerson, ReportShow } from '@/types';

const EMPTY: Record<ReportShow, { title: string; text: string }> = {
    all: {
        title: 'Ningún día en este periodo',
        text: 'Nadie marcó en este centro en esas fechas. Elige otro periodo o revisa el centro arriba.',
    },
    flagged: {
        title: 'Nadie con avisos',
        text: 'Ningún día de este periodo tiene avisos: el reporte está limpio en este punto.',
    },
    unconfirmed: {
        title: 'Nadie sin confirmar',
        text: 'Todas las semanas de este periodo tienen respuesta.',
    },
};

/** The people of the period with their days, a page at a time; or what an empty list means and the way back to all of them. */
export default function ReportTable({
    people,
    show,
    searching,
    all,
}: {
    people: Paginated<ReportPerson>;
    show: ReportShow;
    searching: boolean;
    /** The address of the list with everyone. */
    all: string;
}) {
    if (people.total > 0) {
        return (
            <>
                <StackedTable>
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
                            <ReportPersonRows key={person.id} person={person} />
                        ))}
                    </TableBody>
                </StackedTable>
                <Pagination
                    page={people}
                    label="Páginas de personas"
                    pinned={false}
                />
            </>
        );
    }

    const empty = searching
        ? {
              title: 'Nadie con ese nombre',
              text: 'Revisa cómo lo escribiste o busca con otra parte del nombre.',
          }
        : EMPTY[show];

    return (
        <EmptyState
            icon={CalendarX2}
            title={empty.title}
            action={
                show === 'all' ? undefined : (
                    <Button asChild variant="outline">
                        <Link href={all} preserveScroll>
                            Ver a todas las personas
                        </Link>
                    </Button>
                )
            }
        >
            {empty.text}
        </EmptyState>
    );
}
