import { router } from '@inertiajs/react';
import { useState } from 'react';
import { xsrfToken } from '@/lib/assistant/xsrf';
import type {
    EntryFailure,
    LinkRequestOutcome,
} from '@/lib/worker-access/entry';
import { enterWithKey, requestLink } from '@/lib/worker-access/entry';
import type { SignedKey } from '@/lib/webauthn';

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

function deps() {
    return { fetcher: fetch.bind(window), xsrf: xsrfToken(document.cookie) };
}

/** Entering with the key on this device, or asking for a link by email: working, and how it went. */
export function useWorkerEntry(urls: {
    optionsUrl: string;
    enterUrl: string;
    linkUrl: string;
}) {
    const [working, setWorking] = useState(false);
    const [failure, setFailure] = useState<EntryFailure | null>(null);
    const [linkOutcome, setLinkOutcome] = useState<LinkRequestOutcome | null>(
        null,
    );

    const enter = async () => {
        if (!('PublicKeyCredential' in window)) {
            setFailure('unsupported');

            return;
        }

        setWorking(true);
        setFailure(null);
        const outcome = await enterWithKey(urls, { ...deps(), getKey });
        setWorking(false);

        if (typeof outcome === 'string') {
            setFailure(outcome);
        } else {
            router.visit(outcome.redirect);
        }
    };

    const askLink = async (email: string) => {
        setWorking(true);
        setLinkOutcome(await requestLink(urls.linkUrl, email, deps()));
        setWorking(false);
    };

    return { working, failure, linkOutcome, enter, askLink };
}
