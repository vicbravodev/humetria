import { CalendarClock } from 'lucide-react';
import ChooseBox from '@/components/attendance/choose-box';
import ScheduleRow from '@/components/attendance/schedule-row';
import EmptyState from '@/components/humetria/empty-state';
import Pagination from '@/components/humetria/pagination';
import {
    Table,
    TableBody,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { ScheduleSelection } from '@/hooks/use-schedule-selection';
import type {
    Paginated,
    SchedulePerson,
    ScheduleView,
    WeeklyMaximum,
} from '@/types';

const EMPTY: Record<ScheduleView, { title: string; text: string }> = {
    all: {
        title: 'Aún no hay personas aquí',
        text: 'Da de alta a las personas del centro en Empleados; después fija su horario aquí.',
    },
    scheduled: {
        title: 'Nadie tiene horario todavía',
        text: 'Fija el horario de cada persona desde «Sin horario»: con él se cuentan bien sus horas extra y sus descansos.',
    },
    unscheduled: {
        title: 'Todas las personas tienen horario',
        text: 'Cuando des de alta a alguien nuevo, aparecerá aquí para fijar el suyo.',
    },
};

/** The people of one tab with their schedules, a page at a time, or why there is nobody. */
export default function SchedulesTable({
    people,
    status,
    searching,
    weeklyMaximum,
    canManage,
    selection,
}: {
    people: Paginated<SchedulePerson>;
    status: ScheduleView;
    searching: boolean;
    weeklyMaximum: WeeklyMaximum;
    canManage: boolean;
    selection: ScheduleSelection;
}) {
    if (people.total === 0) {
        return (
            <EmptyState
                icon={CalendarClock}
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
                        {canManage && (
                            <TableHead className="w-10">
                                <ChooseBox
                                    checked={selection.allChosen}
                                    onChange={selection.toggleAll}
                                    name="Elegir a todas las personas de esta página"
                                />
                            </TableHead>
                        )}
                        <TableHead>Persona</TableHead>
                        <TableHead>Semana acordada</TableHead>
                        <TableHead>A la semana</TableHead>
                        <TableHead>Jornada</TableHead>
                        {canManage && (
                            <TableHead>
                                <span className="sr-only">Acciones</span>
                            </TableHead>
                        )}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {people.data.map((person) => (
                        <ScheduleRow
                            key={person.id}
                            person={person}
                            weeklyMaximum={weeklyMaximum}
                            canManage={canManage}
                            chosen={selection.isChosen(person.id)}
                            onChoose={(checked) =>
                                selection.toggle(person.id, checked)
                            }
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
