import CloseDialog from '@/components/engagement/close-dialog';
import DeleteDraftDialog from '@/components/engagement/delete-draft-dialog';
import RemindDialog from '@/components/engagement/remind-dialog';
import type { ClimateParticipation, ClimateSurveyCard } from '@/types';

/** What RH may do with the survey in its state: throw a draft away, or remind and close one in progress. */
export default function SurveyHeaderActions({
    survey,
    canManage,
    participation,
}: {
    survey: ClimateSurveyCard;
    canManage: boolean;
    participation: ClimateParticipation | null;
}) {
    if (!canManage) {
        return null;
    }

    if (survey.status === 'draft') {
        return <DeleteDraftDialog surveyId={survey.id} />;
    }

    if (survey.status === 'closed') {
        return null;
    }

    return (
        <>
            <CloseDialog surveyId={survey.id} />
            {participation?.canRemind === true && (
                <RemindDialog
                    surveyId={survey.id}
                    pending={participation.pending}
                />
            )}
        </>
    );
}
