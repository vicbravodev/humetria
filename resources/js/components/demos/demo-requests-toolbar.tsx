import CountTabs from '@/components/humetria/count-tabs';
import { DEMO_VIEWS, demoQuery } from '@/lib/demo-requests';
import { index } from '@/routes/demo-requests';
import type { DemoFilters, DemoView } from '@/types';

/** The requests by where they stand, each tab with how many it holds. */
export default function DemoRequestsToolbar({
    filters,
    counts,
}: {
    filters: DemoFilters;
    counts: Record<DemoView, number>;
}) {
    return (
        <CountTabs
            label="Estado de las solicitudes"
            tabs={DEMO_VIEWS.map((view) => ({
                label: view.label,
                count: counts[view.value],
                href: index.url({ query: demoQuery(view.value) }),
                active: filters.status === view.value,
            }))}
        />
    );
}
