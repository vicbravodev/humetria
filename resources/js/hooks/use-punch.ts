import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { xsrfToken } from '@/lib/assistant/xsrf';
import { currentPosition } from '@/lib/attendance/geolocation';
import type { LocationError } from '@/lib/attendance/geolocation';
import type { MarkType } from '@/lib/attendance/next-step';
import { punch, punchMessage } from '@/lib/attendance/punch';
import type { PunchPhase } from '@/lib/attendance/punch-phase';
import type { Signer } from '@/lib/attendance/punch';
import { stateAfter } from '@/lib/attendance/punch-state';
import type { PunchState } from '@/lib/attendance/punch-state';
import type { SignedKey } from '@/lib/attendance/webauthn';

/** `navigator.credentials.get()` for a signature; null when the browser gave something else. */
export async function getKey(
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

const NOBODY: PunchState = { person: null, marks: null, feedback: null };

/**
 * Marks from this device. The position is asked at every tap (whoever taps may not be who the screen shows) and kept
 * nowhere; the server drops it when the person does not need it. After each mark the screen shows only what the server
 * said of the person it verified. While it works it says which test it is on (position, the person's fingerprint, face or
 * PIN, the server's check); `retry` marks again what was just tried; `dismiss` closes the answer. «¿No es usted?» makes
 * the browser forget the person.
 */
export function usePunch({
    optionsUrl,
    punchUrl,
    forgetUrl,
    qr,
    initial,
}: {
    optionsUrl: string;
    punchUrl: string;
    forgetUrl: string;
    qr: string | null;
    initial: Signer | null;
}) {
    const [state, setState] = useState<PunchState>({
        ...NOBODY,
        person: initial,
    });
    const [phase, setPhase] = useState<PunchPhase | null>(null);
    const [location, setLocation] = useState<LocationError | null>(null);
    const last = useRef<MarkType | null>(null);

    const mark = async (type: MarkType) => {
        last.current = type;

        if (!('PublicKeyCredential' in window)) {
            setState({
                ...state,
                feedback: {
                    kind: 'error',
                    text: punchMessage('unsupported'),
                    reason: 'unsupported',
                },
            });

            return;
        }

        setPhase('locating');
        setState({ ...state, feedback: null });
        const located = await currentPosition(
            'geolocation' in navigator ? navigator.geolocation : undefined,
        );
        setLocation(located.ok ? null : located.error);
        const outcome = await punch(
            {
                optionsUrl,
                punchUrl,
                type,
                qr,
                position: located.ok ? located.position : null,
            },
            {
                fetcher: fetch.bind(window),
                getKey: async (options) => {
                    setPhase('signing');
                    const key = await getKey(options);
                    setPhase('verifying');

                    return key;
                },
                xsrf: xsrfToken(document.cookie),
            },
        );
        setPhase(null);
        setState(stateAfter(outcome));
    };

    const forget = () => {
        router.post(
            forgetUrl,
            {},
            { preserveScroll: true, onSuccess: () => setState(NOBODY) },
        );
    };

    return {
        ...state,
        phase,
        location,
        mark,
        forget,
        retry: () => {
            if (last.current !== null) {
                void mark(last.current);
            }
        },
        dismiss: () => setState({ ...state, feedback: null }),
    };
}
