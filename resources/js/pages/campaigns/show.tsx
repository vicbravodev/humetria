import { Head, setLayoutProps } from '@inertiajs/react';
import CampaignFollowUp from '@/components/campaigns/campaign-follow-up';
import CampaignHeaderActions from '@/components/campaigns/campaign-header-actions';
import CampaignPageHeader from '@/components/campaigns/campaign-page-header';
import ClosedEvaluation from '@/components/campaigns/closed-evaluation';
import DraftReview from '@/components/campaigns/draft-review';
import { useCampaignAccess } from '@/hooks/use-campaign-access';
import { peoplePage } from '@/lib/campaigns';
import { pendingTotal } from '@/lib/evaluation';
import { index } from '@/routes/tenant/campaigns';
import type {
    AnswersByDay,
    AreaParticipation,
    CampaignDetail,
    CampaignPerson,
    CampaignSending,
    DraftContact,
    InstrumentProgress,
    InviteMissingCount,
    Paginated,
    PeopleFilters,
    ReplacementPool,
    SampledPerson,
    StandingCount,
} from '@/types';

export default function Campaign(props: {
    campaign: CampaignDetail;
    instruments: InstrumentProgress[];
    people: Paginated<CampaignPerson> | null;
    standings: StandingCount[] | null;
    areas: AreaParticipation[] | null;
    answersByDay: AnswersByDay | null;
    contact: DraftContact | null;
    filters: PeopleFilters;
    sending: CampaignSending | null;
    sample: Paginated<SampledPerson> | null;
    launch: { min: string; suggested: string } | null;
    minDeadline: string | null;
    replacementPool: ReplacementPool[] | null;
    missing: InviteMissingCount | null;
}) {
    const { campaign, instruments } = props;
    const { route, company, canManage, canSeeGuideI } = useCampaignAccess(
        campaign.id,
    );
    const people = peoplePage(props.people, props.standings, props.filters);

    setLayoutProps({
        breadcrumbs: [
            { title: 'NOM-035', href: index(route[0]) },
            { title: campaign.workCenter },
        ],
    });

    return (
        <>
            <Head title={`${campaign.kindLabel} · ${campaign.workCenter}`} />
            <div className="flex w-full max-w-[1180px] flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <CampaignPageHeader campaign={campaign} canManage={canManage}>
                    <CampaignHeaderActions
                        tenantSlug={route[0]}
                        campaign={campaign}
                        canManage={canManage}
                        minDeadline={props.minDeadline}
                        replacementPool={props.replacementPool}
                        missing={props.missing ?? { people: 0, links: 0 }}
                        pending={pendingTotal(props.areas)}
                    />
                </CampaignPageHeader>
                {campaign.status === 'draft' && (
                    <DraftReview
                        route={route}
                        company={company}
                        campaign={campaign}
                        draft={props}
                    />
                )}
                {campaign.status === 'active' && (
                    <CampaignFollowUp
                        route={route}
                        campaign={campaign}
                        instruments={instruments}
                        people={people}
                        sending={props.sending}
                        areas={props.areas}
                        answersByDay={props.answersByDay}
                    />
                )}
                {campaign.status === 'closed' && (
                    <ClosedEvaluation
                        route={route}
                        campaign={campaign}
                        instruments={instruments}
                        people={people}
                        canSeeGuideI={canSeeGuideI}
                    />
                )}
            </div>
        </>
    );
}
