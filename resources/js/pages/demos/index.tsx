import { Head } from '@inertiajs/react';
import AdConversionsDownload from '@/components/demos/ad-conversions-download';
import DemoBoard from '@/components/demos/demo-board';
import DemoMetrics from '@/components/demos/demo-metrics';
import DemoRequestsList from '@/components/demos/demo-requests-list';
import DemoRequestsToolbar from '@/components/demos/demo-requests-toolbar';
import DemoViewTabs from '@/components/demos/demo-view-tabs';
import PageHeader from '@/components/humetria/page-header';
import type {
    DemoBoardColumn,
    DemoFilters,
    DemoMetrics as Metrics,
    DemoRequestRow,
    DemoView,
    Paginated,
} from '@/types';

/**
 * Solicitudes de demo (tablero Demos): the title with the funnel or the list beside it and the ad conversions, the four
 * figures, the search with its filters, and the funnel or the list with the request chosen in a fixed panel.
 */
export default function DemoRequests({
    demoRequests,
    board,
    metrics,
    filters,
    counts,
    can_manage: canManage,
}: {
    /** The list, only when it is the view shown. */
    demoRequests: Paginated<DemoRequestRow> | null;
    /** The funnel, only when it is the view shown. */
    board: DemoBoardColumn[] | null;
    /** Arrives after the page shows. */
    metrics?: Metrics;
    filters: DemoFilters;
    counts: Record<DemoView, number>;
    can_manage: boolean;
}) {
    return (
        <>
            <Head title="Solicitudes de demo" />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    title="Solicitudes de demo"
                    description="De la landing al alta: cada solicitud avanza por un embudo, con responsable y siguiente paso. El alta de empresas sigue siendo solo por demo."
                >
                    <DemoViewTabs filters={filters} />
                    <AdConversionsDownload />
                </PageHeader>
                <DemoMetrics metrics={metrics} />
                {(counts.all > 0 || board !== null) && (
                    <DemoRequestsToolbar
                        filters={filters}
                        counts={demoRequests === null ? null : counts}
                    />
                )}
                {board !== null && (
                    <DemoBoard
                        columns={board}
                        filters={filters}
                        canManage={canManage}
                    />
                )}
                {demoRequests !== null && (
                    <DemoRequestsList
                        demoRequests={demoRequests}
                        filters={filters}
                        canManage={canManage}
                    />
                )}
            </div>
        </>
    );
}
