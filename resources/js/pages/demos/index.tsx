import { Head } from '@inertiajs/react';
import DemoRequestsEmpty from '@/components/demos/demo-requests-empty';
import DemoRequestsPagination from '@/components/demos/demo-requests-pagination';
import DemoRequestsTable from '@/components/demos/demo-requests-table';
import { index } from '@/routes/demo-requests';
import type { DemoRequestRow, Paginated } from '@/types';

export default function DemoRequests({
    demoRequests,
}: {
    demoRequests: Paginated<DemoRequestRow>;
}) {
    return (
        <>
            <Head title="Solicitudes de demo" />
            <div className="flex flex-col gap-6 p-4 md:p-8">
                <header className="flex flex-wrap items-end justify-between gap-2">
                    <div>
                        <p className="text-[13px] leading-[18px] font-medium tracking-[0.02em] text-muted-foreground uppercase">
                            Plataforma
                        </p>
                        <h1 className="text-3xl leading-[38px] font-semibold tracking-[-0.01em]">
                            Solicitudes de demo
                        </h1>
                    </div>
                    <p className="text-muted-foreground">
                        {demoRequests.total === 1
                            ? '1 solicitud'
                            : `${demoRequests.total} solicitudes`}
                    </p>
                </header>
                {demoRequests.data.length === 0 ? (
                    <DemoRequestsEmpty />
                ) : (
                    <DemoRequestsTable requests={demoRequests.data} />
                )}
                <DemoRequestsPagination page={demoRequests} />
            </div>
        </>
    );
}

DemoRequests.layout = {
    breadcrumbs: [{ title: 'Solicitudes de demo', href: index() }],
};
