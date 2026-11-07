import { useState } from 'react';
import { xsrfToken } from '@/lib/assistant/xsrf';
import { currentPosition } from '@/lib/attendance/geolocation';
import type { MarkType } from '@/lib/attendance/next-step';
import { doneMessage, punch, punchMessage } from '@/lib/attendance/punch';
import type { PunchDay } from '@/lib/attendance/punch';
import type { SignedKey } from '@/lib/attendance/webauthn';

export type PunchFeedback = { kind: 'done' | 'error'; text: string };

/** `navigator.credentials.get()` for a signature; null when the browser gave something else. */
async function getKey(
    options: PublicKeyCredentialRequestOptions,
): Promise<SignedKey | null> {
    const credential = await navigator.credentials.get({ publicKey: options });

    return credential instanceof PublicKeyCredential &&
        credential.response instanceof AuthenticatorAssertionResponse
        ? {
              rawId: credential.rawId,
              type: credential.type,
              response: credential.response,
          }
        : null;
}

/**
 * Marks from this device: the position only when the page says it may be needed, asked at that moment and kept nowhere;
 * then the signature and the mark. The day the server answers replaces the one shown.
 */
export function usePunch({
    optionsUrl,
    punchUrl,
    qr,
    askLocation,
    initial,
}: {
    optionsUrl: string;
    punchUrl: string;
    qr: string | null;
    askLocation: boolean;
    initial: PunchDay | null;
}) {
    const [day, setDay] = useState(initial);
    const [working, setWorking] = useState(false);
    const [feedback, setFeedback] = useState<PunchFeedback | null>(null);

    const mark = async (type: MarkType) => {
        if (!('PublicKeyCredential' in window)) {
            setFeedback({ kind: 'error', text: punchMessage('unsupported') });

            return;
        }

        setWorking(true);
        setFeedback(null);
        const located = askLocation
            ? await currentPosition(
                  'geolocation' in navigator
                      ? navigator.geolocation
                      : undefined,
              )
            : null;
        const outcome = await punch(
            {
                optionsUrl,
                punchUrl,
                type,
                qr,
                position: located?.ok === true ? located.position : null,
            },
            {
                fetcher: fetch.bind(window),
                getKey,
                xsrf: xsrfToken(document.cookie),
            },
        );
        setWorking(false);

        if (outcome.kind === 'done') {
            setDay(outcome.day);
            setFeedback({ kind: 'done', text: doneMessage(outcome.day) });
        } else {
            setFeedback({ kind: 'error', text: punchMessage(outcome.reason) });
        }
    };

    return { day, working, feedback, mark };
}
