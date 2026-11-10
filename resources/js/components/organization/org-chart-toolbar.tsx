import { Link, router } from '@inertiajs/react';
import Combobox from '@/components/humetria/combobox';
import PrintButton from '@/components/humetria/print-button';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { orgChartQuery } from '@/lib/org-chart/labels';
import { serverSource } from '@/lib/server-options';
import { options as areaOptions } from '@/routes/tenant/areas';
import { index, quality } from '@/routes/tenant/org-chart';
import { search } from '@/routes/tenant/org-chart/people';
import { options as workCenterOptions } from '@/routes/tenant/work-centers';
import type { OrgChartFilters, OrgNode } from '@/types';

const ALL_CENTERS = { value: '', label: 'Todos los centros' };
const ALL_AREAS = { value: '', label: 'Todas las áreas' };

/**
 * Finds a person (and focuses them: the way down to them opens), narrows the tree to a work center or an area, and
 * prints the chart as it is open on screen.
 */
export default function OrgChartToolbar({
    filters,
    person,
}: {
    filters: OrgChartFilters;
    /** The focused person, to keep them while the filters change. */
    person: OrgNode | undefined;
}) {
    const tenantSlug = useTenantSlug();
    const visit = (next: OrgChartFilters, focus = person?.id) =>
        router.get(index.url(tenantSlug), orgChartQuery(next, focus), {
            preserveScroll: true,
        });
    const narrowed =
        person !== undefined ||
        filters.work_center !== null ||
        filters.area !== null;

    return (
        <div className="flex flex-col gap-3 xl:flex-row xl:items-center print:hidden">
            <div
                role="search"
                className="grid gap-3 sm:grid-cols-2 xl:flex xl:min-w-0 xl:flex-1 xl:items-center"
            >
                <div className="sm:col-span-2 xl:max-w-[400px] xl:min-w-0 xl:flex-1">
                    <Combobox
                        id="org-chart-person"
                        name="focus"
                        source={serverSource(search.url(tenantSlug))}
                        defaultValue={
                            person && { value: person.id, label: person.name }
                        }
                        placeholder="Buscar a una persona"
                        onChange={(option) => visit(filters, option.value)}
                    />
                </div>
                <div className="xl:w-48 xl:shrink-0">
                    <Combobox
                        id="org-chart-work-center"
                        name="work_center"
                        source={serverSource(
                            workCenterOptions.url(tenantSlug),
                            [ALL_CENTERS],
                        )}
                        defaultValue={filters.work_center ?? ALL_CENTERS}
                        placeholder="Centro de trabajo"
                        onChange={(option) =>
                            visit({
                                ...filters,
                                work_center:
                                    option.value === '' ? null : option,
                            })
                        }
                    />
                </div>
                <div className="xl:w-44 xl:shrink-0">
                    <Combobox
                        id="org-chart-area"
                        name="area"
                        source={serverSource(areaOptions.url(tenantSlug), [
                            ALL_AREAS,
                        ])}
                        defaultValue={filters.area ?? ALL_AREAS}
                        placeholder="Área"
                        onChange={(option) =>
                            visit({
                                ...filters,
                                area: option.value === '' ? null : option,
                            })
                        }
                    />
                </div>
            </div>
            <div className="flex flex-wrap items-center gap-2 xl:ml-auto xl:shrink-0">
                {narrowed && (
                    <Button variant="ghost" asChild>
                        <Link href={index(tenantSlug)}>Limpiar</Link>
                    </Button>
                )}
                <Button variant="ghost" asChild>
                    <Link href={quality(tenantSlug)}>Calidad de datos</Link>
                </Button>
                <PrintButton variant="outline" label="Imprimir" />
            </div>
        </div>
    );
}
