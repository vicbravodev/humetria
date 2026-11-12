import { Head, Link, setLayoutProps } from '@inertiajs/react';
import PageHeader from '@/components/humetria/page-header';
import Pagination from '@/components/humetria/pagination';
import CalendarEmpty from '@/components/leave/calendar-empty';
import CalendarFilters from '@/components/leave/calendar-filters';
import CalendarGrid from '@/components/leave/calendar-grid';
import CalendarMonthNav from '@/components/leave/calendar-month-nav';
import CalendarWeek from '@/components/leave/calendar-week';
import { useMediaQuery } from '@/hooks/use-media-query';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { calendarQuery, daysOfMonth, monthWord } from '@/lib/leave/calendar';
import { leaveTrail } from '@/lib/leave/trail';
import { countOf } from '@/lib/plural';
import { calendar } from '@/routes/tenant/vacations';
import type {
    LeaveCalendarFilters,
    LeaveCalendarPerson,
    Paginated,
} from '@/types';

/**
 * The team's calendar of a month: who is away each day, by vacations or leave (never which leave). It opens on the people
 * who have an absence, with everybody one click away; narrowed by work center and area, a page of people at a time.
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
    showAll,
    counts,
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
    showAll: boolean;
    counts: { absent: number; everyone: number };
    people: Paginated<LeaveCalendarPerson>;
}) {
    const tenantSlug = useTenantSlug();
    const wide = useMediaQuery('(min-width: 768px)');
    setLayoutProps({ breadcrumbs: leaveTrail('Calendario') });

    return (
        <>
            <Head title="Calendario de vacaciones" />
            <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-6 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader title="Calendario del equipo">
                    <CalendarMonthNav
                        month={month}
                        prevMonth={prevMonth}
                        nextMonth={nextMonth}
                        today={today}
                        filters={filters}
                        showAll={showAll}
                    />
                </PageHeader>
                <CalendarFilters
                    month={month}
                    filters={filters}
                    showAll={showAll}
                    counts={counts}
                />
                {people.data.length === 0 ? (
                    <CalendarEmpty month={month} showAll={showAll} />
                ) : wide ? (
                    <CalendarGrid
                        month={month}
                        today={today}
                        out={out}
                        holidays={holidays}
                        people={people.data}
                        canManage={canManage}
                    />
                ) : (
                    <CalendarWeek
                        days={daysOfMonth(month)}
                        today={today}
                        out={out}
                        people={people.data}
                    />
                )}
                <Pagination page={people} label="Páginas de personas" />
                {!showAll && counts.absent > 0 && (
                    <p className="text-meta text-muted-foreground">
                        {countOf(
                            counts.absent,
                            'persona con ausencias',
                            'personas con ausencias',
                        )}{' '}
                        en {monthWord(month)} ·{' '}
                        <Link
                            href={calendar.url(tenantSlug, {
                                query: calendarQuery(month, filters, true),
                            })}
                            className="touch-hit font-medium text-primary underline-offset-4 hover:underline"
                        >
                            Ver a las {counts.everyone}
                        </Link>
                    </p>
                )}
            </div>
        </>
    );
}
