import { Head } from '@inertiajs/react';
import ConsolePagination from '@/components/console-pagination';
import DemoRequestsEmpty from '@/components/demos/demo-requests-empty';
import DemoRequestsTable from '@/components/demos/demo-requests-table';
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
                <PageHeader caption="Plataforma" title="Solicitudes de demo">
                    <p className="text-muted-foreground">
                        {demoRequests.total === 1
                            ? '1 solicitud'
                            : `${demoRequests.total} solicitudes`}
                    </p>
                </PageHeader>
                {demoRequests.data.length === 0 ? (
                    <DemoRequestsEmpty />
                ) : (
                    <DemoRequestsTable requests={demoRequests.data} />
                )}
                <ConsolePagination
                    page={demoRequests}
                    label="Páginas de solicitudes"
                />
            </div>
        </>
    );
}
