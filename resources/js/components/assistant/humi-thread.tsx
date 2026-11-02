import { ArrowDown } from 'lucide-react';
import { useRef } from 'react';
import HumiEmptyState from '@/components/assistant/humi-empty-state';
import HumiFailure from '@/components/assistant/humi-failure';
import HumiMessage from '@/components/assistant/humi-message';
import HumiThinking from '@/components/assistant/humi-thinking';
import { useStickToBottom } from '@/hooks/use-stick-to-bottom';
import type { HumiChat } from '@/hooks/use-humi-chat';
import { plainText } from '@/lib/assistant/markdown';
import { isWaiting, shownMessages, viewOf } from '@/lib/assistant/message-view';

/**
 * The conversation: messages one under another, anchored to the end while Humi writes unless the person scrolled up, and
 * an announcement for screen readers only once an answer is finished, never word by word.
 */
export default function HumiThread({
    chat,
    suggestions,
    onPick,
}: {
    chat: HumiChat;
    suggestions: readonly string[];
    onPick: (text: string) => void;
}) {
    const list = useRef<HTMLDivElement>(null);
    const { atEnd, scrollToEnd, onScroll } = useStickToBottom(
        list,
        chat.messages,
    );
    const streaming =
        chat.status === 'streaming' || chat.status === 'submitted';
    const waiting = isWaiting(chat.messages, streaming);
    const shown = shownMessages(chat.messages, waiting);
    const lastAnswer = chat.messages.findLast(
        (message) => message.role === 'assistant',
    );

    return (
        <div className="relative min-h-0 flex-1">
            <div
                ref={list}
                onScroll={onScroll}
                role="log"
                aria-label="Conversación con Humi"
                className="flex h-full flex-col gap-5 overflow-y-auto px-5 py-4"
            >
                {shown.length === 0 && !waiting ? (
                    <HumiEmptyState suggestions={suggestions} onPick={onPick} />
                ) : (
                    shown.map((message, index) => (
                        <HumiMessage
                            key={message.id}
                            message={message}
                            streaming={streaming}
                            last={index === shown.length - 1}
                            rating={chat.ratings[message.id] ?? null}
                            canRate={chat.canRate(message.id)}
                            onRate={(rating) =>
                                void chat.rate(message.id, {
                                    rating,
                                    reason: null,
                                })
                            }
                            onRegenerate={chat.retry}
                            onPick={onPick}
                        />
                    ))
                )}
                {waiting && <HumiThinking />}
                {chat.failure !== null && (
                    <HumiFailure failure={chat.failure} onRetry={chat.retry} />
                )}
            </div>
            {!atEnd && (
                <button
                    type="button"
                    onClick={scrollToEnd}
                    className="humi-enter absolute bottom-3 left-1/2 flex -translate-x-1/2 items-center gap-1.5 rounded-full border bg-card px-3 py-1.5 text-sm shadow-float focus-visible:outline-2 focus-visible:outline-ring"
                >
                    <ArrowDown aria-hidden className="size-4" />
                    Ir al final
                </button>
            )}
            <p className="sr-only" aria-live="polite">
                {chat.status === 'ready' && lastAnswer !== undefined
                    ? plainText(viewOf(lastAnswer).text)
                    : ''}
            </p>
        </div>
    );
}
