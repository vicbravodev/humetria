import { useEffect, useState } from 'react';
import { parsePreview, previewError } from '@/lib/leave/preview';
import type { LeavePreview } from '@/types';

type Answer = { preview: LeavePreview | null; error: string | null };

/** The server's answer for some dates, or null when it could not be asked. */
async function ask(url: string, signal: AbortSignal): Promise<Answer | null> {
    try {
        const response = await fetch(url, {
            signal,
            headers: { Accept: 'application/json' },
        });
        const json: unknown = await response.json();

        return response.ok
            ? { preview: parsePreview(json), error: null }
            : { preview: null, error: previewError(json) };
    } catch {
        return null;
    }
}

/**
 * What the picked dates would be as a request, asked each time they change (`url` is null while there is nothing to
 * ask). An answer for other dates is never shown: a newer question makes the older one stale.
 */
export function useLeavePreview(url: string | null): Answer | null {
    const [answered, setAnswered] = useState<{
        url: string;
        answer: Answer;
    } | null>(null);

    useEffect(() => {
        if (url === null) {
            return;
        }

        const controller = new AbortController();

        void ask(url, controller.signal).then((answer) => {
            if (!controller.signal.aborted && answer !== null) {
                setAnswered({ url, answer });
            }
        });

        return () => controller.abort();
    }, [url]);

    return answered !== null && answered.url === url ? answered.answer : null;
}
