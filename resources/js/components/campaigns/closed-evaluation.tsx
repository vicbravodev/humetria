import CampaignHistory from '@/components/campaigns/campaign-history';
import CampaignPeopleSection from '@/components/campaigns/campaign-people-section';
import ClosedSummary from '@/components/campaigns/closed-summary';
import TraumaCard from '@/components/campaigns/trauma-card';
import type { CampaignDetail, InstrumentProgress, PeoplePage } from '@/types';

/**
 * A closed evaluation (pantalla Eval-Cerrada): how many answered, the way to the Guía I channeling for whoever may
 * see it, what happened and when, and who answered for those who manage it.
 */
export default function ClosedEvaluation({
    route,
    campaign,
    instruments,
    people,
    canSeeGuideI,
}: {
    /** The company's slug and the campaign's id. */
    route: [string, string];
    campaign: CampaignDetail;
    instruments: InstrumentProgress[];
    people: PeoplePage | null;
    canSeeGuideI: boolean;
}) {
    const guideI = canSeeGuideI && campaign.instruments.includes('Guía I');

    return (
        <>
            {campaign.progress !== null && (
                <ClosedSummary
                    progress={campaign.progress}
                    guide={campaign.instruments.at(-1) ?? ''}
                />
            )}
            <div className="grid items-start gap-5 md:grid-cols-2">
                {guideI && <TraumaCard route={route} />}
                <CampaignHistory campaign={campaign} />
            </div>
            {people !== null && (
                <CampaignPeopleSection
                    route={route}
                    active={false}
                    people={people}
                    instruments={instruments}
                />
            )}
        </>
    );
}
