import { router } from '@inertiajs/react';
import Combobox from '@/components/humetria/combobox';
import CountTabs from '@/components/humetria/count-tabs';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { calendarQuery } from '@/lib/leave/calendar';
import { serverSource } from '@/lib/server-options';
import { options as areaOptions } from '@/routes/tenant/areas';
import { calendar } from '@/routes/tenant/vacations';
import { options as workCenterOptions } from '@/routes/tenant/work-centers';
import type { LeaveCalendarFilters } from '@/types';

const ALL_CENTERS = { value: '', label: 'Todos los centros' };
const ALL_AREAS = { value: '', label: 'Todas las áreas' };

/** What narrows who is shown: those with an absence or everybody, and the work center and the area. */
export default function CalendarFilters({
    month,
    filters,
    showAll,
    counts,
}: {
    month: string;
    filters: LeaveCalendarFilters;
    showAll: boolean;
    counts: { absent: number; everyone: number };
}) {
    const tenantSlug = useTenantSlug();
    const href = (everybody: boolean) =>
        calendar.url(tenantSlug, {
            query: calendarQuery(month, filters, everybody),
        });
    const narrow = (next: Partial<LeaveCalendarFilters>) =>
        router.get(
            calendar.url(tenantSlug),
            calendarQuery(month, { ...filters, ...next }, showAll),
            { preserveState: true, preserveScroll: true, replace: true },
        );

    return (
        <div role="search" className="flex flex-wrap items-center gap-3">
            <CountTabs
                label="A quién mostrar"
                tabs={[
                    {
                        label: 'Con ausencias',
                        count: counts.absent,
                        active: !showAll,
                        href: href(false),
                    },
                    {
                        label: `Las ${counts.everyone}`,
                        active: showAll,
                        href: href(true),
                    },
                ]}
            />
            <div className="w-full sm:w-56">
                <Combobox
                    id="calendar-work-center"
                    name="work_center"
                    source={serverSource(workCenterOptions.url(tenantSlug), [
                        ALL_CENTERS,
                    ])}
                    defaultValue={filters.center ?? ALL_CENTERS}
                    placeholder="Centro de trabajo"
                    onChange={(option) =>
                        narrow({ center: option.value === '' ? null : option })
                    }
                />
            </div>
            <div className="w-full sm:w-48">
                <Combobox
                    id="calendar-area"
                    name="area"
                    source={serverSource(areaOptions.url(tenantSlug), [
                        ALL_AREAS,
                    ])}
                    defaultValue={filters.area ?? ALL_AREAS}
                    placeholder="Área"
                    onChange={(option) =>
                        narrow({ area: option.value === '' ? null : option })
                    }
                />
            </div>
        </div>
    );
}
