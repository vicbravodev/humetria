import QuestionList from '@/components/engagement/results/question-list';
import type { EngagementItem } from '@/types';

/** The questions answered best and worst by the whole group: where it is strong and where there is room to act. */
export default function HighlightsCard({
    strengths,
    opportunities,
}: {
    strengths: EngagementItem[];
    opportunities: EngagementItem[];
}) {
    return (
        <section
            aria-label="Fortalezas y oportunidades"
            className="grid gap-6 card p-5 sm:p-6 md:grid-cols-2 md:gap-x-10"
        >
            <QuestionList heading="Fortalezas" items={strengths} />
            <QuestionList heading="Oportunidades" items={opportunities} />
        </section>
    );
}
