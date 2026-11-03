import type { UIMessage } from 'ai';
import HumiAvatar from '@/components/assistant/humi-avatar';
import HumiMarkdown from '@/components/assistant/humi-markdown';
import HumiMessageActions from '@/components/assistant/humi-message-actions';
import HumiResultCard from '@/components/assistant/humi-result-card';
import HumiSuggestions from '@/components/assistant/humi-suggestions';
import HumiToolStep from '@/components/assistant/humi-tool-step';
import HumiUserMessage from '@/components/assistant/humi-user-message';
import type { Rating } from '@/lib/assistant/api';
import { viewOf } from '@/lib/assistant/message-view';
import { followUpsFor } from '@/lib/assistant/suggestions';
import { minGroupStep, stepLabel } from '@/lib/assistant/tool-steps';

/**
 * One message of the conversation. Humi's has its face at the side, the lines of what it looked at, its text and cards in
 * order, and, once it is finished, the actions and questions to follow.
 */
export default function HumiMessage({
    message,
    streaming,
    last,
    rating,
    canRate,
    onRate,
    onRegenerate,
    onPick,
}: {
    message: UIMessage;
    streaming: boolean;
    last: boolean;
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
    const minimum = view.items.flatMap((item) =>
        item.kind === 'card' &&
        item.card.render === 'risk_levels' &&
        item.card.minGroup !== null
            ? item.card.minGroup
            : [],
    )[0];

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
                {view.steps.length > 0 && (
                    <ul className="flex flex-col gap-1">
                        {view.steps.map((step) => (
                            <HumiToolStep
                                key={step.id}
                                label={stepLabel(step.tool, step.done)}
                                done={step.done}
                            />
                        ))}
                        {minimum !== undefined && (
                            <HumiToolStep label={minGroupStep(minimum)} done />
                        )}
                    </ul>
                )}
                {view.items.map((item, index) =>
                    item.kind === 'text' ? (
                        <HumiMarkdown key={index} text={item.text} />
                    ) : (
                        <HumiResultCard key={index} card={item.card} />
                    ),
                )}
                {finished && view.text !== '' && (
                    <div className="humi-enter flex flex-col gap-3">
                        <HumiMessageActions
                            text={view.text}
                            rating={rating}
                            canRate={canRate}
                            onRate={onRate}
                            onRegenerate={last ? onRegenerate : null}
                        />
                        {last && (
                            <HumiSuggestions
                                suggestions={followUpsFor(view.renders)}
                                onPick={onPick}
                                variant="chips"
                            />
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}
