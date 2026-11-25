import DemoRequestsEmpty from '@/components/demos/demo-requests-empty';
import DemoRequestsInbox from '@/components/demos/demo-requests-inbox';
import DemoRequestsToolbar from '@/components/demos/demo-requests-toolbar';
import Pagination from '@/components/humetria/pagination';
import type { DemoFilters, DemoRequestRow, DemoView, Paginated } from '@/types';

/** Every request, by status and searched in the server, a page at a time, as an inbox. */
export default function DemoRequestsList({
    demoRequests,
    filters,
    counts,
    canManage,
}: {
    demoRequests: Paginated<DemoRequestRow>;
    filters: DemoFilters;
    counts: Record<DemoView, number>;
    canManage: boolean;
}) {
    return (
        <>
            {counts.all > 0 && (
                <DemoRequestsToolbar filters={filters} counts={counts} />
            )}
            {demoRequests.data.length === 0 ? (
                <DemoRequestsEmpty filters={filters} />
            ) : (
                // Keyed by page and view: a new one opens on its newest request.
                <DemoRequestsInbox
                    key={`${filters.status}-${filters.q}-${demoRequests.current_page}`}
                    requests={demoRequests.data}
                    canManage={canManage}
                />
            )}
            <Pagination
                previous="Ver más recientes"
                next="Ver más antiguas"
                page={demoRequests}
                label="Páginas de solicitudes"
            />
        </>
    );
}
