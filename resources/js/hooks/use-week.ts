import { useState } from 'react';
import { getKey } from '@/hooks/use-punch';
import { xsrfToken } from '@/lib/assistant/xsrf';
import { sendWeek, weekMessage } from '@/lib/attendance/week';
import type { WeekView } from '@/lib/attendance/week';

export type WeekAction = 'read' | 'confirm' | 'dispute';

/**
 * «Mi semana»: every read and every answer asks the device for a signature of its own; nothing of the person is kept in
 * the browser, only on screen until the page closes.
 */
export function useWeek({
    optionsUrl,
    weekUrl,
    start,
}: {
    optionsUrl: string;
    weekUrl: string;
    start: string | null;
}) {
    const [week, setWeek] = useState<WeekView | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [working, setWorking] = useState<WeekAction | null>(null);

    const send = async (
        action: WeekAction,
        url: string,
        body: Record<string, unknown>,
    ): Promise<boolean> => {
        if (!('PublicKeyCredential' in window)) {
            setError(weekMessage('unsupported'));

            return false;
        }

        setWorking(action);
        setError(null);
        const outcome = await sendWeek(
            { optionsUrl, url, body },
            {
                fetcher: fetch.bind(window),
                getKey,
                xsrf: xsrfToken(document.cookie),
            },
        );
        setWorking(null);

        if (outcome.kind === 'failed') {
            setError(weekMessage(outcome.reason));

            return false;
        }

        setWeek(outcome.week);

        return true;
    };

    return {
        week,
        error,
        working,
        read: () => send('read', weekUrl, start === null ? {} : { start }),
        confirm: (url: string) => send('confirm', url, {}),
        dispute: (url: string, reason: string) =>
            send('dispute', url, { reason }),
    };
}
