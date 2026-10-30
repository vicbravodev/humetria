import CloseDialog from '@/components/campaigns/close-dialog';
import ExtendDialog from '@/components/campaigns/extend-dialog';
import PostButton from '@/components/campaigns/post-button';
import ReplacementsDialog from '@/components/campaigns/replacements-dialog';
import { inviteMissing, resend } from '@/routes/tenant/campaigns';
import type { CampaignDetail } from '@/types';

/**
 * What RH does while an evaluation runs: send links again, invite whoever is missing, draw replacements, give more
 * days, close it.
 */
export default function ActiveCampaignActions({
    tenantSlug,
    campaign,
    minDeadline,
}: {
    tenantSlug: string;
    campaign: CampaignDetail;
    minDeadline: string;
}) {
    const progress = campaign.progress;
    const shortBy =
        progress !== null && progress.againstSample
            ? Math.max(progress.target - progress.completed, 0)
            : 0;

    return (
        <div className="flex flex-wrap items-center gap-3">
            <PostButton form={resend.form([tenantSlug, campaign.id])}>
                Reenviar a pendientes
            </PostButton>
            {campaign.kind === 'nom035_evaluation' && (
                <PostButton
                    form={inviteMissing.form([tenantSlug, campaign.id])}
                >
                    Invitar a quien falte
                </PostButton>
            )}
            {campaign.population === 'sample' && progress !== null && (
                <ReplacementsDialog
                    tenantSlug={tenantSlug}
                    campaignId={campaign.id}
                    suggested={progress.target - progress.completed}
                />
            )}
            <ExtendDialog
                tenantSlug={tenantSlug}
                campaignId={campaign.id}
                closesOn={campaign.closesOn}
                min={minDeadline}
            />
            <CloseDialog
                tenantSlug={tenantSlug}
                campaignId={campaign.id}
                shortBy={shortBy}
            />
        </div>
    );
}
