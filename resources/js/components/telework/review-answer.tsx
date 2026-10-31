import ReviewPhotos from '@/components/telework/review-photos';
import { answerLabel } from '@/lib/telework/review';
import type { ReviewAnswer as Answer } from '@/types';

/** One answer: the question, what the person said, their risk note and the photos of that answer. */
export default function ReviewAnswer({
    tenantSlug,
    answer,
    flagged,
}: {
    tenantSlug: string;
    answer: Answer;
    flagged: boolean;
}) {
    return (
        <li className="flex flex-col gap-2 px-5 py-4">
            <p className="text-[15px] leading-[22px]">
                <span className="mr-2 font-mono text-[13px] text-muted-foreground">
                    {answer.code}
                </span>
                {answer.text}
            </p>
            <p
                className={
                    flagged
                        ? 'text-[15px] font-semibold text-destructive'
                        : 'text-[15px] text-muted-foreground'
                }
            >
                {answerLabel(answer.answer)}
            </p>
            {answer.note !== null && (
                <p className="rounded-lg bg-muted px-3 py-2 text-[15px] leading-[22px]">
                    {answer.note}
                </p>
            )}
            <ReviewPhotos tenantSlug={tenantSlug} photos={answer.photos} />
        </li>
    );
}
