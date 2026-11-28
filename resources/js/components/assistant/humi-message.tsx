import type { UIMessage } from 'ai';
import HumiAnswerFooter from '@/components/assistant/humi-answer-footer';
import HumiAvatar from '@/components/humetria/humi-avatar';
import HumiMarkdown from '@/components/assistant/humi-markdown';
import HumiResultCard from '@/components/assistant/humi-result-card';
import HumiToolSteps from '@/components/assistant/humi-tool-steps';
import HumiUserMessage from '@/components/assistant/humi-user-message';
import { Skeleton } from '@/components/ui/skeleton';
import type { Rating } from '@/lib/assistant/api';
import { answerLinksOf } from '@/lib/assistant/answer-links';
import { choiceStateOf } from '@/lib/assistant/chosen-choice';
import { viewOf } from '@/lib/assistant/message-view';
import { followUpsFor } from '@/lib/assistant/suggestions';

/**
 * One message of the conversation. Humi's has its face at the side, the lines of what it looked at, its text and cards in
 * order, and, once it is finished, the actions and questions to follow.
 */
export default function HumiMessage({
    message,
    streaming,
    last,
    following,
    rating,
    canRate,
    onRate,
    onRegenerate,
    onPick,
}: {
    message: UIMessage;
    streaming: boolean;
    last: boolean;
    /** The message after this one, when there is one: the person's answer to a question of buttons. */
    following: UIMessage | undefined;
    rating: Rating | null;
    canRate: boolean;
    onRate: (rating: Rating) => void;
    onRegenerate: () => void;
    onPick: (text: string) => void;
}) {
    if (message.role === 'user') {
        return <HumiUserMessage message={message} />;
    }

    const view = viewOf(message);
    const finished = !(streaming && last);
    return (
        <div className="flex gap-2.5">
            <div className="humi-pop h-fit">
                <HumiAvatar
                    size={28}
                    expression={finished ? 'listo' : 'calcula'}
                    animated={!finished}
                />
            </div>
            <div className="flex min-w-0 flex-1 flex-col gap-3">
                <HumiToolSteps view={view} />
                {!finished && view.items.length === 0 && (
                    <div className="flex flex-col gap-2">
                        <Skeleton className="h-3 w-11/12" />
                        <Skeleton className="h-3 w-3/4" />
                    </div>
                )}
                {view.items.map((item, index) =>
                    item.kind === 'text' ? (
                        <HumiMarkdown key={index} text={item.text} />
                    ) : (
                        <HumiResultCard
                            key={index}
                            card={item.card}
                            choice={choiceStateOf({
                                rows:
                                    item.card.render === 'choices'
                                        ? item.card.rows
                                        : [],
                                last,
                                streaming,
                                next: following,
                                onPick,
                            })}
                        />
                    ),
                )}
                {finished && view.text !== '' && (
                    <HumiAnswerFooter
                        text={view.text}
                        links={answerLinksOf(message)}
                        followUps={last ? followUpsFor(view.renders) : []}
                        rating={rating}
                        canRate={canRate}
                        onRate={onRate}
                        onRegenerate={last ? onRegenerate : null}
                        onPick={onPick}
                    />
                )}
            </div>
        </div>
    );
}
