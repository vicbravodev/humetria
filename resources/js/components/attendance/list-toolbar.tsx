import CenterFilter from '@/components/attendance/center-filter';
import CountTabs from '@/components/humetria/count-tabs';
import SearchField from '@/components/humetria/search-field';
import { usePanelListFilters } from '@/hooks/use-panel-list-filters';
import { panelListQuery } from '@/lib/attendance/devices';
import type { RouteDefinition, RouteQueryOptions } from '@/wayfinder';

type Tab<V extends string> = { status: V; label: string };

/**
 * The top of a list of the registro (pantalla Empleados): search by name and the center at the left, the tabs with their
 * counts at the right. Each is a visit to the same page; the tab keeps the search and the center.
 */
export default function ListToolbar<V extends string>({
    page,
    label,
    tabs,
    center,
    filters,
    counts,
}: {
    page: {
        url: string;
        href: (options: RouteQueryOptions) => RouteDefinition<'get'>;
    };
    label: string;
    tabs: Tab<V>[];
    center: { id: string; name: string } | null;
    filters: { q: string; status: V };
    counts: Record<V, number>;
}) {
    const current = { ...filters, center: center?.id ?? '' };
    const { text, type, filter } = usePanelListFilters(page.url, current);

    return (
        <div className="flex flex-wrap items-center justify-between gap-4">
            <div className="flex w-full flex-col gap-3 sm:flex-row sm:items-center lg:w-auto">
                <SearchField
                    label="Buscar por nombre"
                    value={text}
                    onChange={type}
                    className="w-full sm:max-w-[320px]"
                />
                <CenterFilter
                    id="list-center"
                    center={center}
                    onChange={(id) => filter({ center: id })}
                />
            </div>
            <CountTabs
                label={label}
                tabs={tabs.map((tab) => ({
                    label: tab.label,
                    count: counts[tab.status],
                    href: page.href({
                        query: panelListQuery({
                            ...current,
                            status: tab.status,
                        }),
                    }),
                    active: filters.status === tab.status,
                }))}
            />
        </div>
    );
}
