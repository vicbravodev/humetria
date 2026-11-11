import Combobox from '@/components/humetria/combobox';
import CountTabs from '@/components/humetria/count-tabs';
import SearchField from '@/components/humetria/search-field';
import { useLeaveQueueFilters } from '@/hooks/use-leave-queue-filters';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { queueQuery } from '@/lib/leave/queue';
import { serverSource } from '@/lib/server-options';
import { options as areaOptions } from '@/routes/tenant/areas';
import { index } from '@/routes/tenant/vacations/requests';
import { options as workCenterOptions } from '@/routes/tenant/work-centers';
import type { LeaveQueueFilters, LeaveQueueTab } from '@/types';

const TABS: { tab: LeaveQueueTab; label: string }[] = [
    { tab: 'attend', label: 'Por atender' },
    { tab: 'waiting', label: 'En espera del jefe' },
    { tab: 'approved', label: 'Aprobadas' },
    { tab: 'rejected', label: 'Rechazadas' },
    { tab: 'cancelled', label: 'Canceladas' },
    { tab: 'taken', label: 'Disfrutadas' },
];
const ALL_CENTERS = { value: '', label: 'Todos los centros' };
const ALL_AREAS = { value: '', label: 'Todas las áreas' };

/** Narrows the queue: a tab per state with how many it holds, a search by name, a work center and an area. */
export default function RequestsToolbar({
    filters,
    counts,
}: {
    filters: LeaveQueueFilters;
    counts: Record<LeaveQueueTab, number>;
}) {
    const tenantSlug = useTenantSlug();
    const { text, type, filter } = useLeaveQueueFilters(tenantSlug, filters);

    return (
        <div className="flex flex-col gap-4">
            <CountTabs
                label="Estado de las solicitudes"
                tabs={TABS.map(({ tab, label }) => ({
                    label,
                    count: counts[tab],
                    active: filters.tab === tab,
                    href: index.url(tenantSlug, {
                        query: queueQuery({ ...filters, tab }),
                    }),
                }))}
            />
            <div
                role="search"
                className="grid gap-3 sm:grid-cols-2 xl:flex xl:items-center"
            >
                <SearchField
                    label="Buscar por nombre"
                    value={text}
                    onChange={type}
                    className="sm:col-span-2 xl:w-[360px]"
                />
                <div className="xl:w-52">
                    <Combobox
                        id="filter-work-center"
                        name="center"
                        source={serverSource(
                            workCenterOptions.url(tenantSlug),
                            [ALL_CENTERS],
                        )}
                        defaultValue={filters.center ?? ALL_CENTERS}
                        placeholder="Centro de trabajo"
                        onChange={(option) =>
                            filter({
                                center: option.value === '' ? null : option,
                            })
                        }
                    />
                </div>
                <div className="xl:w-48">
                    <Combobox
                        id="filter-area"
                        name="area"
                        source={serverSource(areaOptions.url(tenantSlug), [
                            ALL_AREAS,
                        ])}
                        defaultValue={filters.area ?? ALL_AREAS}
                        placeholder="Área"
                        onChange={(option) =>
                            filter({
                                area: option.value === '' ? null : option,
                            })
                        }
                    />
                </div>
            </div>
        </div>
    );
}
