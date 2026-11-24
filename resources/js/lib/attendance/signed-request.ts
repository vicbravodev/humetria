import { NETWORK_TIMEOUT_MS } from '@/lib/pwa/sw-rules';
import type { SignedKey } from '@/lib/webauthn';
import { assertionPayload, requestOptions } from '@/lib/webauthn';

export type SignDeps = {
    fetcher: typeof fetch;
    /** `navigator.credentials.get()`, narrowed to a signature or null. */
    getKey: (
        options: PublicKeyCredentialRequestOptions,
    ) => Promise<SignedKey | null>;
    xsrf: string | null;
    /**
     * How long each request may take before it is given up (milliseconds), when the app can go on without the network: the
     * server's challenge, and the request that takes the mark. Without it, no request has a time limit.
     */
    timeouts?: { options: number; request: number };
};

/**
 * Marking waits 4 seconds for the server to offer a challenge (nothing was recorded yet, so the mark can go to the queue)
 * and 20 for the request that takes it: if that one is cut, the server may already have it, and the delivery of the queued
 * copy answers «Ya estaba registrada».
 */
export const PUNCH_TIMEOUTS = {
    options: NETWORK_TIMEOUT_MS,
    request: 20_000,
};

export async function post(
    url: string,
    body: unknown,
    {
        fetcher,
        xsrf,
        timeout,
    }: Pick<SignDeps, 'fetcher' | 'xsrf'> & { timeout?: number | undefined },
): Promise<Response> {
    return fetcher(url, {
        ...(timeout === undefined
            ? {}
            : { signal: AbortSignal.timeout(timeout) }),
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...(xsrf === null ? {} : { 'X-XSRF-TOKEN': xsrf }),
        },
        body: JSON.stringify(body),
    });
}

/** The signature of the device, or why there is none. */
export async function sign(
    options: PublicKeyCredentialRequestOptions,
    deps: Pick<SignDeps, 'getKey'>,
): Promise<SignedKey | string> {
    try {
        return (await deps.getKey(options)) ?? 'failed';
    } catch (error) {
        return error instanceof DOMException && error.name === 'NotAllowedError'
            ? 'cancelled'
            : 'failed';
    }
}

/**
 * Asks the server for a challenge for this browser's session, has the device sign it (the person verifies with their
 * fingerprint, face or PIN) and sends `body` with the signature. The server's answer (of the options when they failed, or
 * of the request), or why nothing was sent: `cancelled` or `failed`.
 */
export type SignedRequest = {
    optionsUrl: string;
    url: string;
    body: Record<string, unknown>;
};

export async function sendSigned(
    { optionsUrl, url, body }: SignedRequest,
    deps: SignDeps,
): Promise<Response | string> {
    const response = await post(
        optionsUrl,
        {},
        {
            ...deps,
            timeout: deps.timeouts?.options,
        },
    );

    if (!response.ok) {
        return response;
    }

    const options = requestOptions(await response.json());
    const key = options === null ? 'failed' : await sign(options, deps);

    return typeof key === 'string'
        ? key
        : post(
              url,
              { ...body, credential: assertionPayload(key) },
              { ...deps, timeout: deps.timeouts?.request },
          );
}
