import type { UIMessage } from 'ai';
import { useCallback, useEffect, useMemo, useState } from 'react';
import {
    latestAnswerId,
    latestConversationId,
    readConversation,
    requestStop,
    sendFeedback,
    type Rating,
    type Thumb,
} from '@/lib/assistant/api';
import { DefaultChatTransport, useChat } from '@/lib/assistant/ai-client';
import { chatFailure, type ChatFailure } from '@/lib/assistant/chat-errors';
import {
    chatContext,
    lastUserText,
    type PageContext,
} from '@/lib/assistant/chat-request';
import { uiMessagesFrom } from '@/lib/assistant/history';
import { xsrfToken } from '@/lib/assistant/xsrf';
import assistant from '@/routes/tenant/assistant';

export type HumiChat = {
    messages: UIMessage[];
    /** submitted and streaming while an answer comes; ready and error otherwise. */
    status: 'submitted' | 'streaming' | 'ready' | 'error';
    failure: ChatFailure | null;
    ratings: Record<string, Rating>;
    /** Whether the server already knows this answer, so a thumb can name it. */
    canRate: (messageId: string) => boolean;
    send: (text: string) => void;
    stop: () => void;
    retry: () => void;
    reset: () => void;
    /** Clears the failure, so the person can write again. */
    dismiss: () => void;
    rate: (messageId: string, thumb: Omit<Thumb, 'messageId'>) => Promise<void>;
};

/**
 * The conversation with Humi in the panel. The browser sends only the last message and the conversation it continues; the
 * server keeps the history. It learns the id of the conversation a first answer started, and the stored id of each
 * answer, so a thumb can name it.
 */
export function useHumiChat(
    slug: string,
    page: PageContext,
    openId: string | null = null,
): HumiChat {
    const [conversationId, setConversationId] = useState<string | null>(null);
    const [answerIds, setAnswerIds] = useState<Record<string, string>>({});
    const [ratings, setRatings] = useState<Record<string, Rating>>({});

    const transport = useMemo(
        () =>
            new DefaultChatTransport<UIMessage>({
                api: assistant.chat.url(slug),
                credentials: 'same-origin',
                headers: () => ({
                    'X-XSRF-TOKEN': xsrfToken(document.cookie) ?? '',
                }),
                // Only the last message goes: the conversation and the page come with each send, the history stays on the server.
                prepareSendMessagesRequest: ({ messages, body }) => ({
                    body: { ...body, message: lastUserText(messages) },
                }),
            }),
        [slug],
    );

    const learn = useCallback(
        async (messageId: string) => {
            const id = conversationId ?? (await latestConversationId(slug));

            setConversationId(id);

            const stored = id === null ? null : await latestAnswerId(slug, id);

            if (stored !== null) {
                setAnswerIds((known) => ({ ...known, [messageId]: stored }));
            }
        },
        [conversationId, slug],
    );

    const chat = useChat<UIMessage>({
        transport,
        onFinish: ({ message, isError }) => {
            if (!isError) {
                void learn(message.id);
            }
        },
    });
    const { setMessages } = chat;
    const context = chatContext(conversationId, page);

    // A conversation of the history opens with what the server kept of it, in plain text.
    useEffect(() => {
        if (openId === null) {
            return;
        }

        let current = true;

        void readConversation(slug, openId).then((body) => {
            if (current) {
                setConversationId(openId);
                setMessages(uiMessagesFrom(body));
            }
        });

        return () => {
            current = false;
        };
    }, [openId, setMessages, slug]);

    return {
        messages: chat.messages,
        status: chat.status,
        failure: chat.error === undefined ? null : chatFailure(chat.error),
        ratings,
        canRate: (messageId) => answerIds[messageId] !== undefined,
        send: (text) => void chat.sendMessage({ text }, { body: context }),
        stop: () => {
            void requestStop(slug);
            void chat.stop();
        },
        retry: () => void chat.regenerate({ body: context }),
        dismiss: () => chat.clearError(),
        reset: () => {
            setConversationId(null);
            setAnswerIds({});
            setRatings({});
            chat.clearError();
            chat.setMessages([]);
        },
        rate: async (messageId, thumb) => {
            const stored = answerIds[messageId];

            if (
                stored !== undefined &&
                (await sendFeedback(slug, { messageId: stored, ...thumb }))
            ) {
                setRatings((known) => ({
                    ...known,
                    [messageId]: thumb.rating,
                }));
            }
        },
    };
}
