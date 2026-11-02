import type { UIMessage } from 'ai';

/** The page the person has open that Humi may use: a campaign or a work center. The server checks it is theirs. */
export type PageContext = {
    kind: 'campaign' | 'work_center';
    id: string;
} | null;

export type ChatBody = {
    message: string;
    conversationId?: string;
    pageContext?: { kind: 'campaign' | 'work_center'; id: string };
};

/** The text of the last message the person wrote: the server keeps the history, the browser sends only this. */
export function lastUserText(messages: readonly UIMessage[]): string {
    const last = messages.findLast((message) => message.role === 'user');

    return (last?.parts ?? [])
        .map((part) => (part.type === 'text' ? part.text : ''))
        .join('');
}

/** What the browser adds to the last message: the conversation it continues and the page the person has open. */
export function chatContext(
    conversationId: string | null,
    page: PageContext,
): Omit<ChatBody, 'message'> {
    return {
        ...(conversationId === null ? {} : { conversationId }),
        ...(page === null ? {} : { pageContext: page }),
    };
}

export function chatBody(
    message: string,
    conversationId: string | null,
    page: PageContext,
): ChatBody {
    return { message, ...chatContext(conversationId, page) };
}
