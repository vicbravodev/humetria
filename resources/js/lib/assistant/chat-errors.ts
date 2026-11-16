export type ChatFailure = {
    /** disabled, budget or rate when the server refused on purpose; error for anything else. */
    reason: string;
    message: string;
    retryAfter: number | null;
};

const GENERIC: ChatFailure = {
    reason: 'error',
    message: 'No pude responder esta vez. Puedes reintentar.',
    retryAfter: null,
};

function isRefusal(
    value: unknown,
): value is { reason: string; message: string } {
    return (
        typeof value === 'object' &&
        value !== null &&
        'reason' in value &&
        typeof value.reason === 'string' &&
        'message' in value &&
        typeof value.message === 'string'
    );
}

function retryAfterOf(refusal: object): number | null {
    return 'retry_after' in refusal && typeof refusal.retry_after === 'number'
        ? refusal.retry_after
        : null;
}

/**
 * What to tell the person when a message failed. The SDK of the chat gives the body of the response as the message of the
 * error: a refusal of the server (Humi off, budget used, too many messages) carries its own words; anything else is a
 * calm, plain message, never a page, a stack or a status.
 */
export function chatFailure(error: Error): ChatFailure {
    try {
        const body: unknown = JSON.parse(error.message);

        if (isRefusal(body)) {
            return {
                reason: body.reason,
                message: body.message,
                retryAfter: retryAfterOf(body),
            };
        }
    } catch {
        // Not JSON: a page or a network error.
    }

    return GENERIC;
}

/**
 * What PostHog learns of a failed answer: that Humi did not answer, as an error of its own. Never the response's body (it
 * can quote the conversation) and nothing for a refusal on purpose, which is not a fault.
 */
export function reportableFailure(error: Error): Error | null {
    if (chatFailure(error).reason !== 'error') {
        return null;
    }

    const reported = new Error('Humi did not answer');
    reported.name = 'HumiStreamError';

    return reported;
}
