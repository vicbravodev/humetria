import CloseDialog from '@/components/campaigns/close-dialog';
import ExtendDialog from '@/components/campaigns/extend-dialog';
import PostButton from '@/components/campaigns/post-button';
import ReplacementsDialog from '@/components/campaigns/replacements-dialog';
import { inviteMissing, resend } from '@/routes/tenant/campaigns';
import type { CampaignDetail, ReplacementPool } from '@/types';

/**
 * What RH does while an evaluation runs: send links again, invite whoever is missing, draw replacements, give more
 * days, close it.
 */
export default function ActiveCampaignActions({
    tenantSlug,
    campaign,
    minDeadline,
    replacementPool,
}: {
    tenantSlug: string;
    campaign: CampaignDetail;
    minDeadline: string;
    replacementPool: ReplacementPool[] | null;
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
            {replacementPool !== null && replacementPool.length > 0 && (
                <ReplacementsDialog
                    tenantSlug={tenantSlug}
                    campaignId={campaign.id}
                    pool={replacementPool}
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
