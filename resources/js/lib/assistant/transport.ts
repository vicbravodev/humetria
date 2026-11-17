import type { UIMessage } from 'ai';
import { DefaultChatTransport } from '@/lib/assistant/ai-client';
import { lastUserText } from '@/lib/assistant/chat-request';
import { xsrfToken } from '@/lib/assistant/xsrf';

/**
 * How the chat reaches the server: only the last message goes (the conversation and the page come with each send, the
 * history stays on the server), and the id of each response (X-Request-Id) is handed back, for support to find a failure.
 */
export function humiTransport(
    url: string,
    onRequestId: (requestId: string | null) => void,
): DefaultChatTransport<UIMessage> {
    return new DefaultChatTransport<UIMessage>({
        api: url,
        credentials: 'same-origin',
        headers: () => ({
            'X-XSRF-TOKEN': xsrfToken(document.cookie) ?? '',
        }),
        prepareSendMessagesRequest: ({ messages, body }) => ({
            body: { ...body, message: lastUserText(messages) },
        }),
        fetch: fetchKeepingRequestId(onRequestId),
    });
}

/** fetch, handing back the id the server gave each response (X-Request-Id, in every log line of it). */
export function fetchKeepingRequestId(
    onRequestId: (requestId: string | null) => void,
): typeof fetch {
    return async (input, init) => {
        const response = await fetch(input, init);

        onRequestId(response.headers.get('X-Request-Id'));

        return response;
    };
}
