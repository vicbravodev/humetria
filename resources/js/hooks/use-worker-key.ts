import { router } from '@inertiajs/react';
import { useState } from 'react';
import { xsrfToken } from '@/lib/assistant/xsrf';
import type { KeyFailure } from '@/lib/worker-access/key';
import { registerKey } from '@/lib/worker-access/key';
import type { CreatedKey } from '@/lib/webauthn';

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

/** Registers a key on this device: working while the device and the server answer, then where to go or why not. */
export function useWorkerKey(request: {
    optionsUrl: string;
    storeUrl: string;
    label: string;
}) {
    const [working, setWorking] = useState(false);
    const [failure, setFailure] = useState<KeyFailure | null>(null);

    const register = async () => {
        if (!('PublicKeyCredential' in window)) {
            setFailure('unsupported');

            return;
        }

        setWorking(true);
        setFailure(null);
        const outcome = await registerKey(request, {
            fetcher: fetch.bind(window),
            createKey,
            xsrf: xsrfToken(document.cookie),
        });
        setWorking(false);

        if (typeof outcome === 'string') {
            setFailure(outcome);
        } else {
            router.visit(outcome.redirect);
        }
    };

    return { working, failure, register };
}
