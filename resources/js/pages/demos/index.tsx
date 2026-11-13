import { Head } from '@inertiajs/react';
import DemoRequestsEmpty from '@/components/demos/demo-requests-empty';
import DemoRequestsInbox from '@/components/demos/demo-requests-inbox';
import DemoRequestsToolbar from '@/components/demos/demo-requests-toolbar';
import PageHeader from '@/components/humetria/page-header';
import Pagination from '@/components/humetria/pagination';
import type { DemoFilters, DemoRequestRow, DemoView, Paginated } from '@/types';

export default function DemoRequests({
    demoRequests,
    filters,
    counts,
    can_manage: canManage,
}: {
    demoRequests: Paginated<DemoRequestRow>;
    filters: DemoFilters;
    counts: Record<DemoView, number>;
    can_manage: boolean;
}) {
    return (
        <>
            <Head title="Solicitudes de demo" />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    title="Solicitudes de demo"
                    description={
                        counts.new === 1
                            ? '1 solicitud nueva por atender.'
                            : `${counts.new} solicitudes nuevas por atender.`
                    }
                />
                {counts.all > 0 && (
                    <DemoRequestsToolbar filters={filters} counts={counts} />
                )}
                {demoRequests.data.length === 0 ? (
                    <DemoRequestsEmpty view={filters.status} />
                ) : (
                    // Keyed by page and view: a new one opens on its newest request.
                    <DemoRequestsInbox
                        key={`${filters.status}-${demoRequests.current_page}`}
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
            </div>
        </>
    );
}
