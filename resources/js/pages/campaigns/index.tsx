import { Head, Link, usePage } from '@inertiajs/react';
import { ClipboardCheck } from 'lucide-react';
import CampaignCard from '@/components/campaigns/campaign-card';
import ClosedCampaignsTable from '@/components/campaigns/closed-campaigns-table';
import WorkCenterEvaluationsTable from '@/components/campaigns/work-center-evaluations-table';
import ConsolePagination from '@/components/console-pagination';
import EmptyState from '@/components/humetria/empty-state';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { create as createTrauma } from '@/routes/tenant/campaigns/trauma';
import type { CampaignSummary, Paginated, WorkCenterEvaluation } from '@/types';

export default function Campaigns({
    open,
    closed,
    workCenters,
}: {
    open: Paginated<CampaignSummary>;
    closed: Paginated<CampaignSummary>;
    workCenters: Paginated<WorkCenterEvaluation>;
}) {
    // Always set here: this page only exists inside a company.
    const { tenant } = usePage().props;
    const tenantSlug = tenant?.slug ?? '';
    const canManage = tenant?.can.manage_campaigns ?? false;

    return (
        <>
            <Head title="NOM-035" />
            <div className="flex flex-col gap-10 px-4 py-6 md:px-10 md:py-8">
                <PageHeader caption="Cumplimiento" title="NOM-035">
                    {canManage && (
                        <Button variant="outline" asChild>
                            <Link href={createTrauma(tenantSlug)}>
                                Guía I por acontecimiento
                            </Link>
                        </Button>
                    )}
                </PageHeader>
                <section className="flex flex-col gap-4">
                    <h2 className="text-xl leading-7 font-semibold">
                        Evaluaciones abiertas
                    </h2>
                    {open.total === 0 ? (
                        <EmptyState
                            icon={ClipboardCheck}
                            title="No hay evaluaciones abiertas"
                        >
                            La NOM-035 se evalúa por centro de trabajo, al menos
                            cada dos años. Empieza una desde la tabla de
                            centros.
                        </EmptyState>
                    ) : (
                        <div className="grid gap-4 lg:grid-cols-2">
                            {open.data.map((campaign) => (
                                <CampaignCard
                                    key={campaign.id}
                                    tenantSlug={tenantSlug}
                                    campaign={campaign}
                                />
                            ))}
                        </div>
                    )}
                    <ConsolePagination
                        page={open}
                        label="Páginas de evaluaciones abiertas"
                    />
                </section>
                <section className="flex flex-col gap-4">
                    <h2 className="text-xl leading-7 font-semibold">
                        Centros de trabajo
                    </h2>
                    <WorkCenterEvaluationsTable
                        tenantSlug={tenantSlug}
                        workCenters={workCenters.data}
                        canManage={canManage}
                    />
                    <ConsolePagination
                        page={workCenters}
                        label="Páginas de centros de trabajo"
                        previous="Anteriores"
                        next="Siguientes"
                    />
                </section>
                {closed.total > 0 && (
                    <section className="mt-2 flex flex-col gap-4">
                        <h2 className="text-xl leading-7 font-semibold">
                            Evaluaciones cerradas
                        </h2>
                        <ClosedCampaignsTable
                            tenantSlug={tenantSlug}
                            campaigns={closed.data}
                        />
                        <ConsolePagination
                            page={closed}
                            label="Páginas de evaluaciones cerradas"
                        />
                    </section>
                )}
            </div>
        </>
    );
}
