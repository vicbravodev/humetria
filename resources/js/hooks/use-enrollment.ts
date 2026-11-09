import { useState } from 'react';
import { xsrfToken } from '@/lib/assistant/xsrf';
import { enrollDevice, enrollMessage } from '@/lib/attendance/enroll';
import type { EnrollRequest } from '@/lib/attendance/enroll';
import type { CreatedKey } from '@/lib/attendance/webauthn';

/** `navigator.credentials.create()` for a new key; null when the browser gave something else. */
async function createKey(
    options: PublicKeyCredentialCreationOptions,
): Promise<CreatedKey | null> {
    const credential = await navigator.credentials.create({
        publicKey: options,
    });

    return credential instanceof PublicKeyCredential &&
        credential.response instanceof AuthenticatorAttestationResponse
        ? {
              rawId: credential.rawId,
              type: credential.type,
              response: credential.response,
          }
        : null;
}

/** Links this device: working while the phone and the server answer, then done or an error to show. */
export function useEnrollment(
    request: Omit<EnrollRequest, 'label'> & {
        label: string;
    },
) {
    const [working, setWorking] = useState(false);
    const [done, setDone] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const enroll = async () => {
        if (!('PublicKeyCredential' in window)) {
            setError(enrollMessage('unsupported'));

            return;
        }

        setWorking(true);
        setError(null);
        const outcome = await enrollDevice(request, {
            fetcher: fetch.bind(window),
            createKey,
            xsrf: xsrfToken(document.cookie),
        });
        setWorking(false);

        if (outcome === 'done') {
            setDone(true);
        } else {
            setError(enrollMessage(outcome));
        }
    };

    return { working, done, error, enroll };
}
