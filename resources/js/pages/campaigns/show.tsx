import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import CampaignFacts from '@/components/campaigns/campaign-facts';
import CampaignStatusBadge from '@/components/campaigns/campaign-status-badge';
import DraftActions from '@/components/campaigns/draft-actions';
import CampaignFollowUp from '@/components/campaigns/campaign-follow-up';
import LaunchForm from '@/components/campaigns/launch-form';
import SampleTable from '@/components/campaigns/sample-table';
import PageHeader from '@/components/page-header';
import { index } from '@/routes/tenant/campaigns';
import type {
    CampaignDetail,
    CampaignPerson,
    InstrumentProgress,
    ReplacementPool,
    SampledPerson,
} from '@/types';

export default function Campaign({
    campaign,
    instruments,
    people,
    sample,
    launch,
    minDeadline,
    replacementPool,
}: {
    campaign: CampaignDetail;
    instruments: InstrumentProgress[];
    people: CampaignPerson[] | null;
    sample: SampledPerson[] | null;
    launch: { min: string; suggested: string } | null;
    minDeadline: string | null;
    replacementPool: ReplacementPool[] | null;
}) {
    const { tenant } = usePage().props;
    const tenantSlug = tenant?.slug ?? '';
    const canManage = tenant?.can.manage_campaigns ?? false;
    const isDraft = campaign.status === 'draft';

    return (
        <>
            <Head title={`${campaign.kindLabel} · ${campaign.workCenter}`} />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <Link
                    href={index(tenantSlug)}
                    className="flex w-fit items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft aria-hidden="true" className="size-4" />
                    NOM-035
                </Link>
                <PageHeader
                    caption={campaign.kindLabel}
                    title={campaign.workCenter}
                >
                    <CampaignStatusBadge
                        status={campaign.status}
                        label={campaign.statusLabel}
                    />
                </PageHeader>
                <CampaignFacts campaign={campaign} />
                {!isDraft && (
                    <CampaignFollowUp
                        tenantSlug={tenantSlug}
                        campaign={campaign}
                        instruments={instruments}
                        people={people}
                        minDeadline={minDeadline}
                        replacementPool={replacementPool}
                    />
                )}
                {isDraft && canManage && (
                    <DraftActions
                        tenantSlug={tenantSlug}
                        campaignId={campaign.id}
                        sampled={campaign.population === 'sample'}
                    />
                )}
                {launch !== null && (
                    <LaunchForm
                        tenantSlug={tenantSlug}
                        campaignId={campaign.id}
                        min={launch.min}
                        suggested={launch.suggested}
                    />
                )}
                {sample !== null && (
                    <section className="flex flex-col gap-4">
                        <h2 className="text-xl leading-7 font-semibold">
                            Muestra sorteada ({sample.length})
                        </h2>
                        <SampleTable people={sample} />
                    </section>
                )}
            </div>
        </>
    );
}
