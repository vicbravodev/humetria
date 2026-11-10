import { Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import Combobox from '@/components/humetria/combobox';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { calendarQuery, monthLabel } from '@/lib/leave/calendar';
import { serverSource } from '@/lib/server-options';
import { options as areaOptions } from '@/routes/tenant/areas';
import { calendar } from '@/routes/tenant/vacations';
import { options as workCenterOptions } from '@/routes/tenant/work-centers';
import type { LeaveCalendarFilters } from '@/types';

const ALL_CENTERS = { value: '', label: 'Todos los centros' };
const ALL_AREAS = { value: '', label: 'Todas las áreas' };

/** The month in view with a step to each side, and the work center and area that narrow who is shown. */
export default function CalendarFilters({
    month,
    prevMonth,
    nextMonth,
    filters,
}: {
    month: string;
    prevMonth: string;
    nextMonth: string;
    filters: LeaveCalendarFilters;
}) {
    const tenantSlug = useTenantSlug();
    const href = (target: string) =>
        calendar.url(tenantSlug, { query: calendarQuery(target, filters) });
    const narrow = (next: Partial<LeaveCalendarFilters>) =>
        router.get(
            calendar.url(tenantSlug),
            calendarQuery(month, { ...filters, ...next }),
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );

    return (
        <div className="flex flex-wrap items-center justify-between gap-4">
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
            </div>
            <div role="search" className="grid w-full gap-3 sm:flex sm:w-auto">
                <div className="sm:w-56">
                    <Combobox
                        id="calendar-work-center"
                        name="work_center"
                        source={serverSource(
                            workCenterOptions.url(tenantSlug),
                            [ALL_CENTERS],
                        )}
                        defaultValue={filters.center ?? ALL_CENTERS}
                        placeholder="Centro de trabajo"
                        onChange={(option) =>
                            narrow({
                                center: option.value === '' ? null : option,
                            })
                        }
                    />
                </div>
                <div className="sm:w-48">
                    <Combobox
                        id="calendar-area"
                        name="area"
                        source={serverSource(areaOptions.url(tenantSlug), [
                            ALL_AREAS,
                        ])}
                        defaultValue={filters.area ?? ALL_AREAS}
                        placeholder="Área"
                        onChange={(option) =>
                            narrow({
                                area: option.value === '' ? null : option,
                            })
                        }
                    />
                </div>
            </div>
        </div>
    );
}
