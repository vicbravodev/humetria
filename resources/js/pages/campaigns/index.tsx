import { Head, usePage } from '@inertiajs/react';
import { ClipboardCheck } from 'lucide-react';
import CampaignCard from '@/components/campaigns/campaign-card';
import ClosedCampaignsTable from '@/components/campaigns/closed-campaigns-table';
import NewEvaluationDialog from '@/components/campaigns/new-evaluation-dialog';
import WorkCenterEvaluationsTable from '@/components/campaigns/work-center-evaluations-table';
import EmptyState from '@/components/humetria/empty-state';
import PageHeader from '@/components/page-header';
import type { CampaignSummary, WorkCenterEvaluation } from '@/types';

export default function Campaigns({
    campaigns,
    workCenters,
}: {
    campaigns: CampaignSummary[];
    workCenters: WorkCenterEvaluation[];
}) {
    // Always set here: this page only exists inside a company.
    const { tenant } = usePage().props;
    const tenantSlug = tenant?.slug ?? '';
    const canManage = tenant?.can.manage_campaigns ?? false;
    const open = campaigns.filter((campaign) => campaign.status !== 'closed');
    const closed = campaigns.filter((campaign) => campaign.status === 'closed');

    return (
        <>
            <Head title="NOM-035" />
            <div className="flex flex-col gap-10 px-4 py-6 md:px-10 md:py-8">
                <PageHeader caption="Cumplimiento" title="NOM-035">
                    {canManage && (
                        <NewEvaluationDialog
                            tenantSlug={tenantSlug}
                            workCenters={workCenters}
                        />
                    )}
                </PageHeader>
                <section className="flex flex-col gap-4">
                    <h2 className="text-xl leading-7 font-semibold">
                        Evaluaciones abiertas
                    </h2>
                    {open.length === 0 ? (
                        <EmptyState
                            icon={ClipboardCheck}
                            title="No hay evaluaciones abiertas"
                        >
                            La NOM-035 se evalúa por centro de trabajo, al menos
                            cada dos años.
                        </EmptyState>
                    ) : (
                        <div className="grid gap-4 lg:grid-cols-2">
                            {open.map((campaign) => (
                                <CampaignCard
                                    key={campaign.id}
                                    tenantSlug={tenantSlug}
                                    campaign={campaign}
                                />
                            ))}
                        </div>
                    )}
                </section>
                <section className="flex flex-col gap-4">
                    <h2 className="text-xl leading-7 font-semibold">
                        Centros de trabajo
                    </h2>
                    <WorkCenterEvaluationsTable
                        tenantSlug={tenantSlug}
                        workCenters={workCenters}
                    />
                </section>
                {closed.length > 0 && (
                    <section className="mt-2 flex flex-col gap-4">
                        <h2 className="text-xl leading-7 font-semibold">
                            Evaluaciones cerradas
                        </h2>
                        <ClosedCampaignsTable
                            tenantSlug={tenantSlug}
                            campaigns={closed}
                        />
                    </section>
                )}
            </div>
        </>
    );
}
