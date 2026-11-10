import AudienceReview from '@/components/engagement/audience-review';
import LaunchForm from '@/components/engagement/launch-form';
import QuestionnairePreview from '@/components/engagement/questionnaire-preview';
import type {
    ClimateDraft,
    ClimateSection,
    ClimateUnreachable,
    Paginated,
} from '@/types';

/**
 * The draft before it is launched (pantalla Eval-Borrador): what RH reviews to the left, and the questions the people
 * will read to the right. Whoever may only view reads the review without the button.
 */
export default function DraftReview({
    surveyId,
    draft,
    unreachable,
    sections,
}: {
    surveyId: string;
    draft: ClimateDraft;
    unreachable: Paginated<ClimateUnreachable>;
    sections: ClimateSection[];
}) {
    return (
        <div className="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(320px,1fr)]">
            {draft.canLaunch ? (
                <LaunchForm
                    surveyId={surveyId}
                    draft={draft}
                    unreachable={unreachable}
                />
            ) : (
                <ol className="card px-6 pt-1 pb-3">
                    <AudienceReview draft={draft} unreachable={unreachable} />
                </ol>
            )}
            <QuestionnairePreview sections={sections} />
        </div>
    );
}
