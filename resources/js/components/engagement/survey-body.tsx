import ActiveFollowUp from '@/components/engagement/active-follow-up';
import ClosedFollowUp from '@/components/engagement/closed-follow-up';
import DraftReview from '@/components/engagement/draft-review';
import type {
    ClimateDraft,
    ClimateParticipation,
    ClimateSection,
    ClimateSurveyCard,
    ClimateUnreachable,
    Paginated,
} from '@/types';

type Props = {
    survey: ClimateSurveyCard;
    draft: ClimateDraft | null;
    unreachable: Paginated<ClimateUnreachable> | null;
    sections: ClimateSection[];
    participation: ClimateParticipation | null;
    resultsUrl: string | null;
};

/** What the survey shows in its state: the draft to review, the follow-up of one in progress, or how a closed one ended. */
export default function SurveyBody({
    survey,
    draft,
    unreachable,
    sections,
    participation,
    resultsUrl,
}: Props) {
    if (draft !== null && unreachable !== null) {
        return (
            <DraftReview
                surveyId={survey.id}
                draft={draft}
                unreachable={unreachable}
                sections={sections}
            />
        );
    }

    if (participation === null) {
        return null;
    }

    return survey.status === 'closed' ? (
        <ClosedFollowUp participation={participation} resultsUrl={resultsUrl} />
    ) : (
        <ActiveFollowUp participation={participation} />
    );
}
