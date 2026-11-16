import { useState } from 'react';
import { getKey } from '@/hooks/use-punch';
import { xsrfToken } from '@/lib/assistant/xsrf';
import {
    acceptAgreement,
    agreementMessage,
} from '@/lib/attendance/punch-agreement';
import type { Agreement } from '@/lib/attendance/punch-agreement';

/**
 * From Marcar, when the person has not accepted the current agreement: «Acepto» signs the acceptance with their key, then
 * `onAccepted` (the mark they asked for is tried again). When the text changed meanwhile, the new one is shown to read.
 */
export function usePunchAgreement({
    optionsUrl,
    url,
    initial,
    onAccepted,
}: {
    optionsUrl: string;
    url: string;
    initial: Agreement;
    onAccepted: () => void;
}) {
    const [agreement, setAgreement] = useState(initial);
    const [sending, setSending] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const accept = async () => {
        if (!('PublicKeyCredential' in window)) {
            setError(agreementMessage('unsupported'));

            return;
        }

        setSending(true);
        setError(null);
        const outcome = await acceptAgreement(
            { optionsUrl, url, version: agreement.version },
            {
                fetcher: fetch.bind(window),
                getKey,
                xsrf: xsrfToken(document.cookie),
            },
        );
        setSending(false);

        if (outcome.kind === 'accepted') {
            onAccepted();
        } else if (outcome.kind === 'outdated') {
            setAgreement(outcome.agreement);
            setError(agreementMessage('outdated'));
        } else {
            setError(agreementMessage(outcome.reason));
        }
    };

    return { agreement, sending, error, accept };
}
