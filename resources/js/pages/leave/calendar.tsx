import { Head, setLayoutProps } from '@inertiajs/react';
import { leaveTrail } from '@/lib/leave/trail';
import { CalendarDays } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import PageHeader from '@/components/humetria/page-header';
import Pagination from '@/components/humetria/pagination';
import CalendarFilters from '@/components/leave/calendar-filters';
import CalendarGrid from '@/components/leave/calendar-grid';
import type {
    LeaveCalendarFilters,
    LeaveCalendarPerson,
    Paginated,
} from '@/types';

/**
 * The team's calendar of a month: who is away each day, by vacations or leave (never which leave), narrowed by work center
 * and area, a page of people at a time.
 */
export default function LeaveCalendar({
    month,
    prevMonth,
    nextMonth,
    today,
    out,
    holidays,
    filters,
    canManage,
    people,
}: {
    month: string;
    prevMonth: string;
    nextMonth: string;
    today: string;
    out: number[];
    holidays: string[];
    filters: LeaveCalendarFilters;
    canManage: boolean;
    people: Paginated<LeaveCalendarPerson>;
}) {
    setLayoutProps({
        breadcrumbs: leaveTrail('Calendario'),
    });

    return (
        <>
            <Head title="Calendario de vacaciones" />
            <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-6 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    caption="Vacaciones"
                    title="Calendario del equipo"
                    description="Quién está fuera cada día del mes, de un vistazo."
                />
                <CalendarFilters
                    month={month}
                    prevMonth={prevMonth}
                    nextMonth={nextMonth}
                    filters={filters}
                />
                {people.data.length === 0 ? (
                    <EmptyState icon={CalendarDays} title="Nadie por mostrar">
                        Ninguna persona coincide con el centro y el área
                        elegidos.
                    </EmptyState>
                ) : (
                    <CalendarGrid
                        month={month}
                        today={today}
                        out={out}
                        holidays={holidays}
                        people={people.data}
                        canManage={canManage}
                    />
                )}
                <Pagination page={people} label="Páginas de personas" />
            </div>
        </>
    );
}
