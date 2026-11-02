import assistant from '@/routes/tenant/assistant';
import { xsrfToken } from '@/lib/assistant/xsrf';

export type Rating = 'up' | 'down';

function headers(): Record<string, string> {
    return {
        Accept: 'application/json',
        'X-XSRF-TOKEN': xsrfToken(document.cookie) ?? '',
    };
}

function isObject(value: unknown): value is object {
    return typeof value === 'object' && value !== null;
}

async function read(url: string): Promise<unknown> {
    try {
        const response = await fetch(url, { headers: headers() });
        const body: unknown = response.ok ? await response.json() : null;

        return body;
    } catch {
        return null;
    }
}

function firstOf(
    list: unknown,
    accept: (item: object) => boolean,
): object | null {
    return Array.isArray(list)
        ? (list.find((item) => isObject(item) && accept(item)) ?? null)
        : null;
}

function idOf(item: object | null): string | null {
    return item !== null && 'id' in item && typeof item.id === 'string'
        ? item.id
        : null;
}

/** The conversation the person was last in: how the browser learns the one a first message started. */
export async function latestConversationId(
    slug: string,
): Promise<string | null> {
    const body = await read(assistant.conversations.index.url(slug));

    return isObject(body) && 'data' in body
        ? idOf(firstOf(body.data, () => true))
        : null;
}

/** The stored id of the last answer of a conversation, which the thumbs refer to. */
export async function latestAnswerId(
    slug: string,
    conversationId: string,
): Promise<string | null> {
    const body = await read(
        assistant.conversations.show.url({
            tenant: slug,
            conversation: conversationId,
        }),
    );

    return isObject(body) && 'messages' in body
        ? idOf(
              firstOf(
                  body.messages,
                  (message) =>
                      'role' in message && message.role === 'assistant',
              ),
          )
        : null;
}

export type Thumb = {
    messageId: string;
    rating: Rating;
    reason: string | null;
};

/** A thumb on an answer; whether the server took it. */
export async function sendFeedback(
    slug: string,
    thumb: Thumb,
): Promise<boolean> {
    try {
        const response = await fetch(assistant.feedback.url(slug), {
            method: 'POST',
            headers: { ...headers(), 'Content-Type': 'application/json' },
            body: JSON.stringify({
                message_id: thumb.messageId,
                rating: thumb.rating,
                reason: thumb.reason,
            }),
        });

        return response.ok;
    } catch {
        return false;
    }
}

/** Presses «Detener» on the server, so the answer asks for no more steps. */
export async function requestStop(slug: string): Promise<void> {
    try {
        await fetch(assistant.chat.stop.url(slug), {
            method: 'POST',
            headers: headers(),
        });
    } catch {
        // The browser already stopped reading the answer.
    }
}

/** What the server keeps of a conversation: its latest messages, newest first. */
export async function readConversation(
    slug: string,
    conversationId: string,
): Promise<unknown> {
    return read(
        assistant.conversations.show.url({
            tenant: slug,
            conversation: conversationId,
        }),
    );
}

/** Deletes a conversation of the person with its messages; whether the server did it. */
export async function deleteConversation(
    slug: string,
    conversationId: string,
): Promise<boolean> {
    try {
        const response = await fetch(
            assistant.conversations.destroy.url({
                tenant: slug,
                conversation: conversationId,
            }),
            { method: 'DELETE', headers: headers() },
        );

        return response.ok;
    } catch {
        return false;
    }
}
