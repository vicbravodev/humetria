import { Head } from '@inertiajs/react';
import AdConversionsDownload from '@/components/demos/ad-conversions-download';
import DemoBoard from '@/components/demos/demo-board';
import DemoMetrics from '@/components/demos/demo-metrics';
import DemoRequestsList from '@/components/demos/demo-requests-list';
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
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    title="Solicitudes de demo"
                    description={
                        counts.new === 1
                            ? '1 solicitud nueva por atender.'
                            : `${counts.new} solicitudes nuevas por atender.`
                    }
                >
                    <AdConversionsDownload />
                </PageHeader>
                <DemoMetrics metrics={metrics} />
                <DemoViewTabs view={filters.view} />
                {board !== null && (
                    <DemoBoard columns={board} canManage={canManage} />
                )}
                {demoRequests !== null && (
                    <DemoRequestsList
                        demoRequests={demoRequests}
                        filters={filters}
                        counts={counts}
                        canManage={canManage}
                    />
                )}
            </div>
        </>
    );
}
