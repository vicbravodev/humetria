import ListToolbar from '@/components/attendance/list-toolbar';
import SchedulesTable from '@/components/attendance/schedules-table';
import ScheduleSelectionBar from '@/components/attendance/schedule-selection-bar';
import Notice from '@/components/humetria/notice';
import WeekRibbonLegend from '@/components/humetria/week-ribbon-legend';
import { useScheduleSelection } from '@/hooks/use-schedule-selection';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index } from '@/routes/tenant/attendance/schedules';
import type { AttendanceSchedulesProps, ScheduleView } from '@/types';

const TABS: { status: ScheduleView; label: string }[] = [
    { status: 'all', label: 'Todas' },
    { status: 'scheduled', label: 'Con horario' },
    { status: 'unscheduled', label: 'Sin horario' },
];

/** The people and their agreed schedules: those without one first called out, then a tab per state, searched by name. */
export default function SchedulePeople({
    center,
    filters,
    counts,
    weeklyMaximum,
    people,
    canManage,
}: AttendanceSchedulesProps) {
    const tenantSlug = useTenantSlug();
    const selection = useScheduleSelection(people.data);

    return (
        <section aria-labelledby="personas" className="flex flex-col gap-4">
            <h2 id="personas" className="sr-only">
                Personas y sus horarios
            </h2>
            {counts.unscheduled > 0 && (
                <Notice>
                    <strong>
                        {counts.unscheduled === 1
                            ? '1 persona no tiene horario.'
                            : `${counts.unscheduled} personas no tienen horario.`}
                    </strong>{' '}
                    Sin horario se cuentan 8 h cada día y ningún día de
                    descanso: sus horas extra pueden salir de más. Elígelas y
                    fíjales uno en un solo paso.
                </Notice>
            )}
            <ListToolbar
                page={{
                    url: index.url(tenantSlug),
                    href: (options) => index(tenantSlug, options),
                }}
                label="Horario"
                tabs={TABS}
                center={center}
                filters={filters}
                counts={counts}
            />
            {canManage && selection.chosen.length > 0 && (
                <ScheduleSelectionBar
                    chosen={selection.chosen}
                    weeklyMaximum={weeklyMaximum}
                    onClear={selection.clear}
                />
            )}
            <SchedulesTable
                people={people}
                status={filters.status}
                searching={filters.q !== ''}
                weeklyMaximum={weeklyMaximum}
                canManage={canManage}
                selection={selection}
            />
            <WeekRibbonLegend />
        </section>
    );
}
