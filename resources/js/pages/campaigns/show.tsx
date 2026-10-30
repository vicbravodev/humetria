import { Form, Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import CampaignFacts from '@/components/campaigns/campaign-facts';
import CampaignStatusBadge from '@/components/campaigns/campaign-status-badge';
import DeleteDraftDialog from '@/components/campaigns/delete-draft-dialog';
import InstrumentsProgress from '@/components/campaigns/instruments-progress';
import LaunchForm from '@/components/campaigns/launch-form';
import SampleTable from '@/components/campaigns/sample-table';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { index, redraw } from '@/routes/tenant/campaigns';
import type {
    CampaignDetail,
    InstrumentProgress,
    SampledPerson,
} from '@/types';

export default function Campaign({
    campaign,
    instruments,
    sample,
    launch,
}: {
    campaign: CampaignDetail;
    instruments: InstrumentProgress[];
    sample: SampledPerson[] | null;
    launch: { min: string; suggested: string } | null;
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
                {!isDraft && <InstrumentsProgress instruments={instruments} />}
                {isDraft && canManage && (
                    <div className="flex flex-wrap items-center gap-3">
                        {campaign.population === 'sample' && (
                            <Form {...redraw.form([tenantSlug, campaign.id])}>
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        Volver a sortear
                                    </Button>
                                )}
                            </Form>
                        )}
                        <DeleteDraftDialog
                            tenantSlug={tenantSlug}
                            campaignId={campaign.id}
                        />
                    </div>
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
