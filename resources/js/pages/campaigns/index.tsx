import { Head, usePage } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import CampaignActions from '@/components/campaigns/campaign-actions';
import CampaignHistory from '@/components/campaigns/campaign-history';
import GuideSizes from '@/components/campaigns/guide-sizes';
import TraumaEventsSection from '@/components/campaigns/trauma-events-section';
import WorkCenterCard from '@/components/campaigns/work-center-card';
import ConsolePagination from '@/components/console-pagination';
import EmptyState from '@/components/humetria/empty-state';
import PageHeader from '@/components/page-header';
import type {
    CampaignSummary,
    CentersBySize,
    Paginated,
    WorkCenterEvaluation,
} from '@/types';

export default function Campaigns({
    events,
    closed,
    workCenters,
    centersBySize,
    minGroup,
}: {
    events: Paginated<CampaignSummary>;
    closed: Paginated<CampaignSummary>;
    workCenters: Paginated<WorkCenterEvaluation>;
    centersBySize: CentersBySize;
    minGroup: number;
}) {
    // Always set here: this page only exists inside a company.
    const { tenant } = usePage().props;
    const tenantSlug = tenant?.slug ?? '';
    const canManage = tenant?.can.manage_campaigns ?? false;

    return (
        <>
            <Head title="NOM-035" />
            <div className="flex w-full max-w-[1180px] flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    title="NOM-035"
                    description={
                        // The mockup's 620 px measure keeps the actions beside it at 1440 px.
                        <span className="inline-block max-w-[620px]">
                            Factores de riesgo psicosocial. Evalúa cada centro
                            de trabajo, entrega los resultados y conserva la
                            evidencia para la STPS.
                        </span>
                    }
                >
                    {canManage && (
                        <CampaignActions
                            tenantSlug={tenantSlug}
                            minGroup={minGroup}
                        />
                    )}
                </PageHeader>
                <section
                    aria-label="Centros de trabajo"
                    className="flex flex-col gap-4"
                >
                    {workCenters.total === 0 ? (
                        <EmptyState
                            icon={Building2}
                            title="Sin centros de trabajo"
                        >
                            La NOM-035 se evalúa por centro de trabajo. Da de
                            alta el primero para empezar.
                        </EmptyState>
                    ) : (
                        <div className="grid grid-cols-[repeat(auto-fit,minmax(min(440px,100%),1fr))] gap-5">
                            {workCenters.data.map((center) => (
                                <WorkCenterCard
                                    key={center.id}
                                    tenantSlug={tenantSlug}
                                    center={center}
                                    canManage={canManage}
                                    minGroup={minGroup}
                                />
                            ))}
                        </div>
                    )}
                    <ConsolePagination
                        page={workCenters}
                        label="Páginas de centros de trabajo"
                        previous="Anteriores"
                        next="Siguientes"
                        pinned={false}
                    />
                </section>
                <GuideSizes centers={workCenters.data} counts={centersBySize} />
                <TraumaEventsSection tenantSlug={tenantSlug} events={events} />
                <CampaignHistory tenantSlug={tenantSlug} closed={closed} />
            </div>
        </>
    );
}
