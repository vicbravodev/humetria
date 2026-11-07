import { useState } from 'react';
import { getKey } from '@/hooks/use-punch';
import { xsrfToken } from '@/lib/assistant/xsrf';
import { correctionBody, correctionReason } from '@/lib/attendance/correction';
import type { CorrectionDraft } from '@/lib/attendance/correction';
import { sendWeek, weekMessage } from '@/lib/attendance/week';
import type { WeekView } from '@/lib/attendance/week';

export type WeekAction = 'read' | 'confirm' | 'dispute' | 'correct';

/**
 * «Mi semana»: every read and every answer asks the device for a signature of its own; nothing of the person is kept in
 * the browser, only on screen until the page closes.
 */
export function useWeek({
    optionsUrl,
    weekUrl,
    start,
    correctionOptionsUrl,
    correctionUrl,
}: {
    optionsUrl: string;
    weekUrl: string;
    start: string | null;
    correctionOptionsUrl: string;
    correctionUrl: string;
}) {
    const [week, setWeek] = useState<WeekView | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [working, setWorking] = useState<WeekAction | null>(null);
    const [notice, setNotice] = useState<string | null>(null);

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
        setNotice(null);
        const outcome = await sendWeek(
            {
                // A correction is signed on a challenge of its own.
                optionsUrl:
                    action === 'correct' ? correctionOptionsUrl : optionsUrl,
                url,
                body,
            },
            {
                fetcher: fetch.bind(window),
                getKey,
                xsrf: xsrfToken(document.cookie),
            },
        );
        setWorking(null);

        if (outcome.kind === 'failed') {
            // A week that changed since it was read comes back as it is now: shown, to be read again before answering.
            if (outcome.week !== undefined) {
                setWeek(outcome.week);
            }

            setError(
                weekMessage(
                    action === 'correct'
                        ? correctionReason(outcome.reason)
                        : outcome.reason,
                ),
            );

            return false;
        }

        setWeek(outcome.week);
        setNotice(
            action === 'correct'
                ? 'Su solicitud llegó a Recursos Humanos. Aquí verá su respuesta.'
                : null,
        );

        return true;
    };

    return {
        week,
        error,
        notice,
        working,
        read: () => send('read', weekUrl, start === null ? {} : { start }),
        confirm: (url: string) =>
            send('confirm', url, { digest: week?.digest }),
        dispute: (url: string, reason: string) =>
            send('dispute', url, { reason, digest: week?.digest }),
        // The answer is the week with the request in it.
        correct: (draft: CorrectionDraft) =>
            send(
                'correct',
                correctionUrl,
                correctionBody(draft, week?.week.start ?? null),
            ),
    };
}
