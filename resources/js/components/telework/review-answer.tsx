import ReviewPhotos from '@/components/telework/review-photos';
import { answerLabel } from '@/lib/telework/review';
import type { ReviewAnswer as Answer } from '@/types';

/** One answer: the question, what the person said, their risk note and the photos of that answer. */
export default function ReviewAnswer({
    answer,
    flagged,
}: {
    answer: Answer;
    flagged: boolean;
}) {
    return (
        <li className="flex flex-col gap-2 px-5 py-4">
            <p className="text-meta">
                <span className="mr-2 font-mono text-caption text-muted-foreground">
                    {answer.code}
                </span>
                {answer.text}
            </p>
            <p
                className={
                    flagged
                        ? 'text-meta font-semibold text-destructive'
                        : 'text-meta text-muted-foreground'
                }
            >
                {answerLabel(answer.answer)}
            </p>
            {answer.note !== null && (
                <p className="rounded-lg bg-muted px-3 py-2 text-meta">
                    {answer.note}
                </p>
            )}
            <ReviewPhotos photos={answer.photos} />
        </li>
    );
}
