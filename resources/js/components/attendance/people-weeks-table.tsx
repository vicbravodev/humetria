import { Users } from 'lucide-react';
import PersonWeekRow from '@/components/attendance/person-week-row';
import EmptyState from '@/components/humetria/empty-state';
import Pagination from '@/components/humetria/pagination';
import {
    Table,
    TableBody,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { OverviewPerson, OverviewView, Paginated } from '@/types';

const EMPTY: Record<OverviewView, { title: string; text: string }> = {
    all: {
        title: 'Nadie en este centro',
        text: 'Cuando des de alta a sus personas, aquí verás su semana.',
    },
    pending: {
        title: 'Nadie sin confirmar',
        text: 'Todas las personas respondieron por esta semana.',
    },
    disputed: {
        title: 'Ninguna semana objetada',
        text: 'Cuando alguien diga que su semana no está bien, aparecerá aquí con su motivo.',
    },
    confirmed: {
        title: 'Aún nadie confirma',
        text: 'Las personas confirman su semana desde «Mi semana» cuando termina el domingo.',
    },
    at_risk: {
        title: 'Ninguna semana en riesgo',
        text: 'Nadie rebasó un límite ni se acercó al tope de horas extra esta semana.',
    },
};

/** The people of one tab, a page at a time, or why there is nobody. */
export default function PeopleWeeksTable({
    people,
    status,
    searching,
    over,
}: {
    people: Paginated<OverviewPerson>;
    status: OverviewView;
    searching: boolean;
    over: boolean;
}) {
    if (people.total === 0) {
        return (
            <EmptyState
                icon={Users}
                title={searching ? 'Nadie con ese nombre' : EMPTY[status].title}
            >
                {searching
                    ? 'Revisa cómo lo escribiste o busca con otra parte del nombre.'
                    : EMPTY[status].text}
            </EmptyState>
        );
    }

    return (
        <>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Persona</TableHead>
                        <TableHead>Centro</TableHead>
                        <TableHead>Horas de la semana</TableHead>
                        <TableHead>Avisos</TableHead>
                        <TableHead>Semana</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {people.data.map((person) => (
                        <PersonWeekRow
                            key={person.id}
                            person={person}
                            over={over}
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
    );
}
