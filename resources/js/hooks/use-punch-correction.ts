import { useState } from 'react';
import { getKey } from '@/hooks/use-punch';
import { xsrfToken } from '@/lib/assistant/xsrf';
import { correctionBody, correctionReason } from '@/lib/attendance/correction';
import type { CorrectionDraft } from '@/lib/attendance/correction';
import type { PunchFeedback } from '@/lib/attendance/punch-state';
import { sendWeek, weekMessage } from '@/lib/attendance/week';

/**
 * From Marcar, when a mark was refused: the person asks RH to add it, with a signature of its own. Nothing of the person
 * is kept; the screen only says the request arrived.
 */
export function usePunchCorrection({
    optionsUrl,
    url,
}: {
    optionsUrl: string;
    url: string;
}) {
    const [working, setWorking] = useState(false);
    const [feedback, setFeedback] = useState<PunchFeedback | null>(null);

    const ask = async (draft: CorrectionDraft): Promise<boolean> => {
        if (!('PublicKeyCredential' in window)) {
            setFeedback({ kind: 'error', text: weekMessage('unsupported') });

            return false;
        }

        setWorking(true);
        setFeedback(null);
        const outcome = await sendWeek(
            { optionsUrl, url, body: correctionBody(draft, null) },
            {
                fetcher: fetch.bind(window),
                getKey,
                xsrf: xsrfToken(document.cookie),
            },
        );
        setWorking(false);
        setFeedback(
            outcome.kind === 'done'
                ? {
                      kind: 'done',
                      text: 'Su solicitud llegó a Recursos Humanos. Verá su respuesta en «Mi semana».',
                  }
                : {
                      kind: 'error',
                      text: weekMessage(correctionReason(outcome.reason)),
                  },
        );

        return outcome.kind === 'done';
    };

    return { working, feedback, ask };
}
