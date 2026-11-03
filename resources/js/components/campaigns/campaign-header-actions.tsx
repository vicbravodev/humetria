import { Link } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import ActiveCampaignActions from '@/components/campaigns/active-campaign-actions';
import DeleteDraftDialog from '@/components/campaigns/delete-draft-dialog';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { report, results } from '@/routes/tenant/campaigns';
import type {
    CampaignDetail,
    InviteMissingCount,
    ReplacementPool,
} from '@/types';

/**
 * The actions at the right of the evaluation's header, by its state: throw the draft away; remind, extend and the rest
 * while it runs; the results and the 7.7 report once closed.
 */
export default function CampaignHeaderActions({
    campaign,
    canManage,
    minDeadline,
    replacementPool,
    pending,
    guide,
    missing,
}: {
    campaign: CampaignDetail;
    canManage: boolean;
    minDeadline: string | null;
    replacementPool: ReplacementPool[] | null;
    pending: number;
    guide: string | null;
    missing: InviteMissingCount;
}) {
    const tenantSlug = useTenantSlug();
    const route: [string, string] = [tenantSlug, campaign.id];

    if (campaign.status === 'draft') {
        return canManage ? (
            <DeleteDraftDialog campaignId={campaign.id} />
        ) : null;
    }

    if (campaign.status === 'active' && canManage) {
        return (
            <ActiveCampaignActions
                campaign={campaign}
                minDeadline={minDeadline}
                replacementPool={replacementPool}
                pending={pending}
                guide={guide}
                missing={missing}
            />
        );
    }

    return (
        <>
            {campaign.status === 'closed' &&
                campaign.kind === 'nom035_evaluation' && (
                    <Button asChild variant="outline">
                        <Link href={report(route)}>
                            <FileText aria-hidden="true" />
                            Informe 7.7
                        </Link>
                    </Button>
                )}
            <Button asChild>
                <Link href={results(route)}>Ver resultados</Link>
            </Button>
        </>
    );
}
