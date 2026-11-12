import { router } from '@inertiajs/react';
import { ChevronDown, ChevronsDownUp, ChevronsUpDown } from 'lucide-react';
import Combobox from '@/components/humetria/combobox';
import OrgPersonSearch from '@/components/organization/org-person-search';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { DEPTHS, depthLabel } from '@/lib/org-chart/canvas-labels';
import { orgChartQuery } from '@/lib/org-chart/labels';
import { serverSource } from '@/lib/server-options';
import { cn } from '@/lib/utils';
import { index } from '@/routes/tenant/org-chart';
import { options as workCenterOptions } from '@/routes/tenant/work-centers';
import type { OrgChartFilters, OrgNode } from '@/types';

const ALL_CENTERS = { value: '', label: 'Todos los centros' };

/**
 * The bar over the canvas: finds a person (the page reloads focused on them, lighting their way from the top), narrows
 * the chart to a work center, chooses how many levels are open and opens or closes everything.
 */
export default function OrgChartToolbar({
    filters,
    person,
    depth,
    reserve,
}: {
    filters: OrgChartFilters;
    /** The focused person, to keep them while the filters change. */
    person: OrgNode | undefined;
    depth: {
        levels: number | null;
        onChange: (levels: number) => void;
    };
    /** Leaves room on the right for the panel of the person selected. */
    reserve: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const visit = (next: OrgChartFilters, focus = person?.id) =>
        router.get(index.url(tenantSlug), orgChartQuery(next, focus), {
            preserveScroll: true,
        });
    const everything = depth.levels === Infinity;

    return (
        <div
            role="search"
            className={cn(
                'pointer-events-none absolute top-3 left-3 z-10 flex flex-wrap gap-2 print:hidden',
                reserve ? 'right-[384px]' : 'right-3',
            )}
        >
            <div className="pointer-events-auto w-full sm:w-[280px]">
                <OrgPersonSearch filters={filters} person={person} />
            </div>
            <div className="pointer-events-auto w-[210px]">
                <Combobox
                    id="org-chart-work-center"
                    name="work_center"
                    source={serverSource(workCenterOptions.url(tenantSlug), [
                        ALL_CENTERS,
                    ])}
                    defaultValue={filters.work_center ?? ALL_CENTERS}
                    placeholder="Centro de trabajo"
                    onChange={(option) =>
                        visit({
                            ...filters,
                            work_center: option.value === '' ? null : option,
                        })
                    }
                />
            </div>
            <DropdownMenu modal={false}>
                <DropdownMenuTrigger asChild>
                    <Button variant="outline" className="pointer-events-auto">
                        {depthLabel(depth.levels)}
                        <ChevronDown aria-hidden />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start">
                    <DropdownMenuRadioGroup
                        value={String(depth.levels)}
                        onValueChange={(value) => depth.onChange(Number(value))}
                    >
                        {DEPTHS.map((levels) => (
                            <DropdownMenuRadioItem
                                key={levels}
                                value={String(levels)}
                            >
                                {depthLabel(levels).replace('Mostrar: ', '')}
                            </DropdownMenuRadioItem>
                        ))}
                    </DropdownMenuRadioGroup>
                </DropdownMenuContent>
            </DropdownMenu>
            <Button
                variant="outline"
                className="pointer-events-auto"
                onClick={() => depth.onChange(everything ? 1 : Infinity)}
            >
                {everything ? <ChevronsDownUp /> : <ChevronsUpDown />}
                {everything ? 'Contraer' : 'Expandir todo'}
            </Button>
        </div>
    );
}
