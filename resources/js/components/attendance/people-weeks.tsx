import PeopleWeeksTable from '@/components/attendance/people-weeks-table';
import CountTabs from '@/components/humetria/count-tabs';
import SearchField from '@/components/humetria/search-field';
import { useOverviewFilters } from '@/hooks/use-overview-filters';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { overviewQuery } from '@/lib/attendance/overview';
import { index } from '@/routes/tenant/attendance';
import type { AttendanceOverviewProps, OverviewView } from '@/types';

const TABS: { status: OverviewView; label: string }[] = [
    { status: 'all', label: 'Todas' },
    { status: 'pending', label: 'Sin confirmar' },
    { status: 'disputed', label: 'Objetadas' },
    { status: 'confirmed', label: 'Confirmadas' },
    { status: 'at_risk', label: 'Topes en riesgo' },
];

/** The people of the week person by person: a tab per answer and for the weeks at risk, searched by name. */
export default function PeopleWeeks({
    week,
    center,
    filters,
    counts,
    people,
}: Pick<
    AttendanceOverviewProps,
    'week' | 'center' | 'filters' | 'counts' | 'people'
>) {
    const tenantSlug = useTenantSlug();
    const current = {
        ...filters,
        week: week.start,
        center: center?.id ?? '',
    };
    const { text, type } = useOverviewFilters(tenantSlug, current);

    return (
        <section aria-labelledby="personas" className="flex flex-col gap-4">
            <h2 id="personas" className="text-xl leading-7 font-semibold">
                Persona por persona
            </h2>
            <div className="flex flex-wrap items-center justify-between gap-4">
                <CountTabs
                    label="Estado de la semana"
                    tabs={TABS.map((tab) => ({
                        label: tab.label,
                        count: counts[tab.status],
                        href: index(tenantSlug, {
                            query: overviewQuery({
                                ...current,
                                status: tab.status,
                            }),
                        }),
                        active: filters.status === tab.status,
                    }))}
                />
                <SearchField
                    label="Buscar por nombre"
                    value={text}
                    onChange={type}
                    className="w-full sm:max-w-[320px]"
                />
            </div>
            <PeopleWeeksTable
                people={people}
                status={filters.status}
                searching={filters.q !== ''}
                over={week.over}
            />
        </section>
    );
}
