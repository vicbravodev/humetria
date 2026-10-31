import { Head } from '@inertiajs/react';
import ConsolePagination from '@/components/console-pagination';
import DemoRequestsEmpty from '@/components/demos/demo-requests-empty';
import DemoRequestsInbox from '@/components/demos/demo-requests-inbox';
import PageHeader from '@/components/page-header';
import type { DemoRequestRow, Paginated } from '@/types';

export default function DemoRequests({
    demoRequests,
}: {
    demoRequests: Paginated<DemoRequestRow>;
}) {
    return (
        <>
            <Head title="Solicitudes de demo" />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    title="Solicitudes de demo"
                    description={
                        demoRequests.total === 1
                            ? '1 solicitud, la más reciente primero.'
                            : `${demoRequests.total} solicitudes, la más reciente primero.`
                    }
                />
                {demoRequests.data.length === 0 ? (
                    <DemoRequestsEmpty />
                ) : (
                    // Keyed by page: a new page opens on its newest request.
                    <DemoRequestsInbox
                        key={demoRequests.current_page}
                        requests={demoRequests.data}
                    />
                )}
                <ConsolePagination
                    page={demoRequests}
                    label="Páginas de solicitudes"
                />
            </div>
        </>
    );
}
