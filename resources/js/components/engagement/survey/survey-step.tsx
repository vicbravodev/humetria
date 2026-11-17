import ReadyToSend from '@/components/engagement/survey/ready-to-send';
import SurveyIntro from '@/components/engagement/survey/survey-intro';
import SurveyNote from '@/components/engagement/survey/survey-note';
import SurveyQuestion from '@/components/engagement/survey/survey-question';
import type { useEngagementSurvey } from '@/hooks/use-engagement-survey';
import type { EngagementSurveyProps } from '@/types';

/** The screen of the current step: the introduction, a section's instruction, one question, or the count before sending. */
export default function SurveyStep({
    survey,
    flow,
}: {
    survey: EngagementSurveyProps;
    flow: ReturnType<typeof useEngagementSurvey>;
}) {
    const { step } = flow;

    switch (step.kind) {
        case 'intro':
            return <SurveyIntro survey={survey} />;
        case 'question':
            return (
                <SurveyQuestion
                    // A new question is a new element: its heading takes the focus as it mounts.
                    key={step.item.key}
                    survey={survey}
                    flow={flow}
                    section={step.section}
                    item={step.item}
                />
            );
        case 'section':
            return (
                <SurveyNote title={step.title}>{step.instruction}</SurveyNote>
            );
        case 'submit':
            return <ReadyToSend tally={flow.tally} onReview={flow.review} />;
    }
}
