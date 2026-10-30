import ActiveCampaignActions from '@/components/campaigns/active-campaign-actions';
import CampaignPeopleSection from '@/components/campaigns/campaign-people-section';
import InstrumentsProgress from '@/components/campaigns/instruments-progress';
import SendingProgress from '@/components/campaigns/sending-progress';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { progressNote } from '@/lib/campaigns';
import type {
    CampaignDetail,
    CampaignSending,
    InstrumentProgress,
    PeoplePage,
    ReplacementPool,
} from '@/types';

/**
 * A launched campaign: how many answered each guide, whether the Guía III sample is met (III.2 c), and, for those who
 * manage it, who answered.
 */
export default function CampaignFollowUp({
    tenantSlug,
    campaign,
    instruments,
    people,
    sending,
    minDeadline,
    replacementPool,
}: {
    tenantSlug: string;
    minDeadline: string | null;
    replacementPool: ReplacementPool[] | null;
    campaign: CampaignDetail;
    instruments: InstrumentProgress[];
    people: PeoplePage | null;
    sending: CampaignSending | null;
}) {
    const progress = campaign.progress;
    const sampleShort =
        progress !== null &&
        progress.againstSample &&
        progress.completed < progress.target;

    const active = campaign.status === 'active' && people !== null;

    return (
        <>
            {active && minDeadline !== null && (
                <ActiveCampaignActions
                    tenantSlug={tenantSlug}
                    campaign={campaign}
                    minDeadline={minDeadline}
                    replacementPool={replacementPool}
                />
            )}
            {sampleShort && (
                <Alert>
                    <AlertDescription>
                        {progressNote(progress)} para el mínimo de la muestra
                        que pide la norma ({progress.target}). Si alguien no
                        contestará, sortea reemplazos.
                    </AlertDescription>
                </Alert>
            )}
            {sending !== null && <SendingProgress sending={sending} />}
            <InstrumentsProgress instruments={instruments} />
            {people !== null && (
                <CampaignPeopleSection
                    tenantSlug={tenantSlug}
                    campaignId={campaign.id}
                    active={active}
                    people={people}
                    instruments={instruments}
                />
            )}
        </>
    );
}
