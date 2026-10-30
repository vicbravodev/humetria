import { Link } from '@inertiajs/react';
import CampaignStatusBadge from '@/components/campaigns/campaign-status-badge';
import { Button } from '@/components/ui/button';
import { results } from '@/routes/tenant/campaigns';
import type { CampaignDetail } from '@/types';

/** The campaign's state and, once launched, the way to its results. */
export default function CampaignHeaderActions({
    tenantSlug,
    campaign,
}: {
    tenantSlug: string;
    campaign: CampaignDetail;
}) {
    return (
        <div className="flex items-center gap-3">
            <CampaignStatusBadge
                status={campaign.status}
                label={campaign.statusLabel}
            />
            {campaign.status !== 'draft' && (
                <Button asChild variant="outline">
                    <Link href={results([tenantSlug, campaign.id])}>
                        Resultados
                    </Link>
                </Button>
            )}
        </div>
    );
}
