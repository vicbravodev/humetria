import CountTabs from '@/components/humetria/count-tabs';
import SearchField from '@/components/humetria/search-field';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { DEMO_VIEWS, demoListQuery } from '@/lib/demo-requests';
import { index } from '@/routes/demo-requests';
import type { DemoFilters, DemoView } from '@/types';

/** The requests by where they stand, each tab with how many it holds, and a search the server runs. */
export default function DemoRequestsToolbar({
    filters,
    counts,
}: {
    filters: DemoFilters;
    counts: Record<DemoView, number>;
}) {
    const { text, type } = useQueryFilters(index.url(), filters, demoListQuery);

    return (
        <div role="search" className="flex flex-col gap-4">
            <SearchField
                label="Buscar empresa, contacto, correo o WhatsApp"
                value={text}
                onChange={type}
                className="w-full md:w-[360px]"
            />
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
        </div>
    );
}
