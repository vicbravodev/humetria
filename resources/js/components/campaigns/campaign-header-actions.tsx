import { Link, usePage } from '@inertiajs/react';
import { ClipboardList, FileText } from 'lucide-react';
import ActiveCampaignActions from '@/components/campaigns/active-campaign-actions';
import DeleteDraftDialog from '@/components/campaigns/delete-draft-dialog';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { program, report, results } from '@/routes/tenant/campaigns';
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
    // The Programa de intervención exists for an evaluation scored by risk level (Guía II or III), for who may read it.
    const canSeeProgram =
        usePage().props.tenant?.can.view_interventions === true &&
        campaign.instruments.some((guide) => guide !== 'Guía I');

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
                    <>
                        {canSeeProgram && (
                            <Button asChild variant="outline">
                                <Link href={program(route)}>
                                    <ClipboardList aria-hidden="true" />
                                    Programa
                                </Link>
                            </Button>
                        )}
                        <Button asChild variant="outline">
                            <Link href={report(route)}>
                                <FileText aria-hidden="true" />
                                Informe 7.7
                            </Link>
                        </Button>
                    </>
                )}
            <Button asChild>
                <Link href={results(route)}>Ver resultados</Link>
            </Button>
        </>
    );
}
