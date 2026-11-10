import AgreementQuestion from '@/components/engagement/survey/agreement-question';
import OpenQuestion from '@/components/engagement/survey/open-question';
import RecommendQuestion from '@/components/engagement/survey/recommend-question';
import type { useEngagementSurvey } from '@/hooks/use-engagement-survey';
import type { SurveyQuestion as Item } from '@/lib/engagement/survey-flow';
import type { EngagementSurveyProps } from '@/types';

/** One question of the survey, by how it is answered: agreement rows, a 0 to 10 scale, or free text. */
export default function SurveyQuestion({
    survey,
    flow,
    section,
    item,
}: {
    survey: EngagementSurveyProps;
    flow: ReturnType<typeof useEngagementSurvey>;
    section: number;
    item: Item;
}) {
    const name = `item-${item.key}`;
    const topic = survey.sections[section]?.title ?? '';

    if (item.scale === 'open') {
        return (
            <OpenQuestion
                name={name}
                topic={topic}
                text={item.text}
                notice={survey.openNotice}
                value={flow.comments[item.key] ?? ''}
                onChange={(text) => flow.comment(item.key, text)}
            />
        );
    }

    const common = {
        name,
        topic,
        text: item.text,
        value: flow.answers[item.key],
        onChange: (value: number) => flow.answer(item.key, value),
    };

    return item.scale === 'recommend' ? (
        <RecommendQuestion {...common} />
    ) : (
        <AgreementQuestion {...common} labels={survey.agreementLabels} />
    );
}
