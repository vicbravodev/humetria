import { usePage } from '@inertiajs/react';
import CountTabs from '@/components/humetria/count-tabs';
import SearchField from '@/components/humetria/search-field';
import DemoFilterChip from '@/components/demos/demo-filter-chip';
import { useQueryFilters } from '@/hooks/use-query-filters';
import {
    DEMO_ORIGINS,
    DEMO_SIZES,
    DEMO_VIEWS,
    demoListQuery,
} from '@/lib/demo-requests';
import { index } from '@/routes/demo-requests';
import type { DemoFilters, DemoOwner, DemoView } from '@/types';

/**
 * The search and the filters of the console (tablero Demos): a search box that grows and the chips Origen, Responsable
 * and Tamaño, all run by the server; the list adds its statuses with how many each holds.
 */
export default function DemoRequestsToolbar({
    filters,
    counts,
}: {
    filters: DemoFilters;
    /** Only in the list: the funnel shows the statuses as its columns. */
    counts: Record<DemoView, number> | null;
}) {
    const { staff } = usePage<{ staff?: DemoOwner[] }>().props;
    const { text, type, filter } = useQueryFilters(
        index.url(),
        filters,
        demoListQuery,
    );
    const owners = [
        { value: 'none', label: 'Sin asignar' },
        ...(staff ?? []).map((person) => ({
            value: String(person.id),
            label: person.name,
        })),
    ];

    return (
        <div role="search" className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center gap-3">
                <SearchField
                    label="Buscar empresa o contacto"
                    value={text}
                    onChange={type}
                    className="min-w-[220px] flex-1"
                />
                <DemoFilterChip
                    name="Origen"
                    idle="Origen: todos"
                    options={DEMO_ORIGINS}
                    filter={{
                        value: filters.origin ?? '',
                        choose: (origin) => filter({ origin }),
                    }}
                />
                <DemoFilterChip
                    name="Responsable"
                    options={owners}
                    filter={{
                        value: filters.owner ?? '',
                        choose: (owner) => filter({ owner }),
                    }}
                />
                <DemoFilterChip
                    name="Tamaño"
                    options={DEMO_SIZES}
                    filter={{
                        value: filters.size ?? '',
                        choose: (size) => filter({ size }),
                    }}
                />
            </div>
            {counts !== null && (
                <CountTabs
                    label="Estado de las solicitudes"
                    tabs={DEMO_VIEWS.map((view) => ({
                        label: view.label,
                        count: counts[view.value],
                        href: index.url({
                            query: demoListQuery({
                                ...filters,
                                status: view.value,
                            }),
                        }),
                        active: filters.status === view.value,
                    }))}
                />
            )}
        </div>
    );
}
