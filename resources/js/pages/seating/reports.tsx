import { Head, setLayoutProps } from '@inertiajs/react';
import { Factory } from 'lucide-react';
import CountTabs from '@/components/humetria/count-tabs';
import PageHeader from '@/components/humetria/page-header';
import ReportsTable from '@/components/seating/reports-table';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { seatingCrumbs } from '@/lib/seating/crumbs';
import { index as reportsIndex } from '@/routes/tenant/seating/reports';
import type { Paginated, SeatReportRow } from '@/types';

export default function SeatingReports({
    workCenter,
    reports,
    status,
    openCount,
    resolvedCount,
    canResolve,
}: {
    workCenter: { id: string; name: string };
    reports: Paginated<SeatReportRow>;
    status: 'open' | 'resolved';
    openCount: number;
    resolvedCount: number;
    canResolve: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const route = { tenant: tenantSlug, workCenter: workCenter.id };
    setLayoutProps({
        breadcrumbs: seatingCrumbs(tenantSlug, workCenter, {
            title: 'Reportes de asientos',
        }),
    });

    return (
        <>
            <Head title={`Reportes de asientos · ${workCenter.name}`} />
            <div className="flex w-full flex-col gap-6 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    title="Reportes de asientos"
                    description="Lo que las personas avisan desde su página. No se guarda quién avisó."
                    facts={[{ icon: Factory, text: workCenter.name }]}
                />
                <CountTabs
                    label="Estado de los reportes"
                    tabs={[
                        {
                            label: 'Abiertos',
                            count: openCount,
                            href: reportsIndex(route),
                            active: status === 'open',
                        },
                        {
                            label: 'Resueltos',
                            count: resolvedCount,
                            href: reportsIndex(route, {
                                query: { status: 'resolved' },
                            }),
                            active: status === 'resolved',
                        },
                    ]}
                />
                <ReportsTable
                    reports={reports}
                    open={status === 'open'}
                    canResolve={canResolve}
                />
            </div>
        </>
    );
}
