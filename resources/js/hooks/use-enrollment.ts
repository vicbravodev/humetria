import { useState } from 'react';
import { xsrfToken } from '@/lib/assistant/xsrf';
import { enrollDevice } from '@/lib/attendance/enroll';
import type { EnrollOutcome, EnrollRequest } from '@/lib/attendance/enroll';
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

/** Links this device: working while the phone and the server answer, then done or how it failed (to say what to do). */
export function useEnrollment(
    request: Omit<EnrollRequest, 'label'> & {
        label: string;
    },
) {
    const [working, setWorking] = useState(false);
    const [done, setDone] = useState(false);
    const [failure, setFailure] = useState<Exclude<
        EnrollOutcome,
        'done'
    > | null>(null);

    const enroll = async () => {
        if (!('PublicKeyCredential' in window)) {
            setFailure('unsupported');

            return;
        }

        setWorking(true);
        setFailure(null);
        const outcome = await enrollDevice(request, {
            fetcher: fetch.bind(window),
            createKey,
            xsrf: xsrfToken(document.cookie),
        });
        setWorking(false);

        if (outcome === 'done') {
            setDone(true);
        } else {
            setFailure(outcome);
        }
    };

    return {
        working,
        done,
        failure,
        enroll,
    };
}
