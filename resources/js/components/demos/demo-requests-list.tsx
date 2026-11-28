import DemoRequestsEmpty from '@/components/demos/demo-requests-empty';
import DemoRequestsInbox from '@/components/demos/demo-requests-inbox';
import Pagination from '@/components/humetria/pagination';
import type { DemoFilters, DemoRequestRow, Paginated } from '@/types';

/** Every request the filters leave, searched in the server, a page at a time, with the one chosen in the panel. */
export default function DemoRequestsList({
    demoRequests,
    filters,
    canManage,
}: {
    demoRequests: Paginated<DemoRequestRow>;
    filters: DemoFilters;
    canManage: boolean;
}) {
    return (
        <>
            {demoRequests.data.length === 0 ? (
                <DemoRequestsEmpty filters={filters} />
            ) : (
                // Keyed by page and filters: a new one opens on its newest request.
                <DemoRequestsInbox
                    key={`${JSON.stringify(filters)}-${demoRequests.current_page}`}
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
