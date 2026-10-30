import { Search } from 'lucide-react';
import Combobox from '@/components/humetria/combobox';
import CountTabs from '@/components/humetria/count-tabs';
import { Input } from '@/components/ui/input';
import { useEmployeeFilters } from '@/hooks/use-employee-filters';
import { employeeQuery } from '@/lib/employees';
import { serverSource } from '@/lib/server-options';
import { options as areaOptions } from '@/routes/tenant/areas';
import { index } from '@/routes/tenant/employees';
import { options as workCenterOptions } from '@/routes/tenant/work-centers';
import type { EmployeeCounts, EmployeeFilters } from '@/types';

const ALL_CENTERS = { value: '', label: 'Todos los centros' };
const ALL_AREAS = { value: '', label: 'Todas las áreas' };

/** Narrows the list: text in the name, email or number; a work center; an area; and the active or deactivated people. */
export default function EmployeesToolbar({
    tenantSlug,
    filters,
    counts,
}: {
    tenantSlug: string;
    filters: EmployeeFilters;
    counts: EmployeeCounts;
}) {
    const { text, type, filter } = useEmployeeFilters(tenantSlug, filters);
    const tab = (status: EmployeeFilters['status']) => ({
        count: status === 'active' ? counts.active : counts.inactive,
        active: filters.status === status,
        href: index.url(tenantSlug, {
            query: employeeQuery({ ...filters, status, without_email: false }),
        }),
    });

    return (
        <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
            <div
                role="search"
                className="grid gap-3 sm:grid-cols-2 lg:flex lg:items-center"
            >
                <div className="relative sm:col-span-2 lg:w-[400px]">
                    <Search
                        aria-hidden
                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        type="search"
                        aria-label="Buscar por nombre, correo o número"
                        placeholder="Buscar por nombre, correo o número"
                        value={text}
                        onChange={(event) => type(event.target.value)}
                        className="pl-10"
                    />
                </div>
                <div className="lg:w-48">
                    <Combobox
                        id="filter-work-center"
                        name="work_center"
                        source={serverSource(
                            workCenterOptions.url(tenantSlug),
                            [ALL_CENTERS],
                        )}
                        defaultValue={filters.work_center ?? ALL_CENTERS}
                        placeholder="Centro de trabajo"
                        onChange={(option) =>
                            filter({
                                work_center:
                                    option.value === '' ? null : option,
                            })
                        }
                    />
                </div>
                <div className="lg:w-44">
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
            <div className="lg:ml-auto">
                <CountTabs
                    label="Estado de las personas"
                    tabs={[
                        { label: 'Activos', ...tab('active') },
                        { label: 'Bajas', ...tab('inactive') },
                    ]}
                />
            </div>
        </div>
    );
}
