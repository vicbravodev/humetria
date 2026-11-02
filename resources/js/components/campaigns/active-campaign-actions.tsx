import { Send } from 'lucide-react';
import { useState } from 'react';
import CampaignMoreMenu from '@/components/campaigns/campaign-more-menu';
import CloseDialog from '@/components/campaigns/close-dialog';
import ExtendDialog from '@/components/campaigns/extend-dialog';
import InviteMissingDialog from '@/components/campaigns/invite-missing-dialog';
import ReplacementsDialog from '@/components/campaigns/replacements-dialog';
import ResendFilteredDialog from '@/components/campaigns/resend-filtered-dialog';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import type {
    CampaignDetail,
    CampaignDialog,
    InviteMissingCount,
    ReplacementPool,
} from '@/types';

const PENDING = { status: 'pending', guide: null, area: null, q: '' };

/**
 * What RH does while an evaluation runs (pantalla Eval-Activa): remind everyone pending and give more days; the menu
 * has the results so far, inviting whoever is missing, drawing replacements and closing it.
 */
export default function ActiveCampaignActions({
    campaign,
    minDeadline,
    replacementPool,
    pending,
    missing,
}: {
    campaign: CampaignDetail;
    /** The earliest new last day; null once the last day has passed, when it can no longer be extended. */
    minDeadline: string | null;
    replacementPool: ReplacementPool[] | null;
    /** People waiting with a link sent: who "Recordar" reaches. */
    pending: number;
    /** Who «Invitar a quien falte» sends a link to. */
    missing: InviteMissingCount;
}) {
    const tenantSlug = useTenantSlug();
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
            {minDeadline !== null && (
                <ExtendDialog
                    campaignId={campaign.id}
                    closesOn={campaign.closesOn}
                    min={minDeadline}
                />
            )}
            <ResendFilteredDialog
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
                missing={missing}
                open={dialog === 'invite'}
                onOpenChange={toggle('invite')}
            />
            <CloseDialog
                campaignId={campaign.id}
                shortBy={shortBy}
                open={dialog === 'close'}
                onOpenChange={toggle('close')}
            />
            {replacementPool !== null && (
                <ReplacementsDialog
                    campaignId={campaign.id}
                    pool={replacementPool}
                    open={dialog === 'replacements'}
                    onOpenChange={toggle('replacements')}
                />
            )}
        </>
    );
}
