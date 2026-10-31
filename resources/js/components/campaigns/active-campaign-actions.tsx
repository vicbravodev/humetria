import { Send } from 'lucide-react';
import { useState } from 'react';
import CampaignMoreMenu from '@/components/campaigns/campaign-more-menu';
import CloseDialog from '@/components/campaigns/close-dialog';
import ExtendDialog from '@/components/campaigns/extend-dialog';
import InviteMissingDialog from '@/components/campaigns/invite-missing-dialog';
import ReplacementsDialog from '@/components/campaigns/replacements-dialog';
import ResendFilteredDialog from '@/components/campaigns/resend-filtered-dialog';
import { Button } from '@/components/ui/button';
import type { CampaignDetail, CampaignDialog, ReplacementPool } from '@/types';

const PENDING = { status: 'pending', guide: null, area: null, q: '' };

/**
 * What RH does while an evaluation runs (pantalla Eval-Activa): remind everyone pending and give more days; the menu
 * has the results so far, inviting whoever is missing, drawing replacements and closing it.
 */
export default function ActiveCampaignActions({
    tenantSlug,
    campaign,
    minDeadline,
    replacementPool,
    pending,
}: {
    tenantSlug: string;
    campaign: CampaignDetail;
    minDeadline: string;
    replacementPool: ReplacementPool[] | null;
    /** People waiting with a link sent: who "Recordar" reaches. */
    pending: number;
}) {
    const [dialog, setDialog] = useState<CampaignDialog | null>(null);
    const toggle = (name: CampaignDialog) => (open: boolean) =>
        setDialog(open ? name : null);
    const progress = campaign.progress;
    const shortBy =
        progress !== null && progress.againstSample
            ? Math.max(progress.target - progress.completed, 0)
            : 0;

    return (
        <>
            <CampaignMoreMenu
                route={[tenantSlug, campaign.id]}
                kind={campaign.kind}
                canReplace={
                    replacementPool !== null && replacementPool.length > 0
                }
                onOpen={setDialog}
            />
            <ExtendDialog
                tenantSlug={tenantSlug}
                campaignId={campaign.id}
                closesOn={campaign.closesOn}
                min={minDeadline}
            />
            <ResendFilteredDialog
                tenantSlug={tenantSlug}
                campaignId={campaign.id}
                filters={PENDING}
                count={pending}
                trigger={
                    <Button>
                        <Send aria-hidden="true" />
                        Recordar a {pending}
                    </Button>
                }
            />
            <InviteMissingDialog
                route={[tenantSlug, campaign.id]}
                open={dialog === 'invite'}
                onOpenChange={toggle('invite')}
            />
            <CloseDialog
                tenantSlug={tenantSlug}
                campaignId={campaign.id}
                shortBy={shortBy}
                open={dialog === 'close'}
                onOpenChange={toggle('close')}
            />
            {replacementPool !== null && (
                <ReplacementsDialog
                    tenantSlug={tenantSlug}
                    campaignId={campaign.id}
                    pool={replacementPool}
                    open={dialog === 'replacements'}
                    onOpenChange={toggle('replacements')}
                />
            )}
        </>
    );
}
