import ReviewAnswer from '@/components/telework/review-answer';
import type { ReviewAnswer as Answer } from '@/types';

/** A block of answers under its title; the unfavorable ones come first and are marked. */
export default function ReviewAnswerGroup({
    tenantSlug,
    title,
    description,
    answers,
    flagged = false,
}: {
    tenantSlug: string;
    title: string;
    description: string;
    answers: Answer[];
    flagged?: boolean;
}) {
    if (answers.length === 0) {
        return null;
    }

    return (
        <section className="overflow-hidden rounded-xl border bg-card">
            <header className="border-b bg-muted px-5 py-3">
                <h2 className="text-lg font-semibold">
                    {title} ({answers.length})
                </h2>
                <p className="text-[15px] text-muted-foreground">
                    {description}
                </p>
            </header>
            <ul className="divide-y">
                {answers.map((answer) => (
                    <ReviewAnswer
                        key={answer.id}
                        tenantSlug={tenantSlug}
                        answer={answer}
                        flagged={flagged}
                    />
                ))}
            </ul>
        </section>
    );
}
