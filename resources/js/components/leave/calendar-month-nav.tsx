import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { calendarQuery, monthLabel } from '@/lib/leave/calendar';
import { calendar } from '@/routes/tenant/vacations';
import type { LeaveCalendarFilters } from '@/types';

/** The month in view with a step to each side and «Hoy» to come back to the current one; the filters travel with it. */
export default function CalendarMonthNav({
    month,
    prevMonth,
    nextMonth,
    today,
    filters,
    showAll,
}: {
    month: string;
    prevMonth: string;
    nextMonth: string;
    today: string;
    filters: LeaveCalendarFilters;
    showAll: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const href = (target: string) =>
        calendar.url(tenantSlug, {
            query: calendarQuery(target, filters, showAll),
        });
    const current = today.slice(0, 7);

    return (
        <div className="flex items-center gap-2">
            <Button variant="outline" size="icon" asChild>
                <Link
                    href={href(prevMonth)}
                    aria-label="Mes anterior"
                    preserveScroll
                >
                    <ChevronLeft aria-hidden />
                </Link>
            </Button>
            <h2
                className="min-w-44 text-center text-lg font-semibold first-letter:uppercase"
                aria-live="polite"
            >
                {monthLabel(month)}
            </h2>
            <Button variant="outline" size="icon" asChild>
                <Link
                    href={href(nextMonth)}
                    aria-label="Mes siguiente"
                    preserveScroll
                >
                    <ChevronRight aria-hidden />
                </Link>
            </Button>
            {month !== current && (
                <Button variant="ghost" asChild>
                    <Link href={href(current)} preserveScroll>
                        Hoy
                    </Link>
                </Button>
            )}
        </div>
    );
}
