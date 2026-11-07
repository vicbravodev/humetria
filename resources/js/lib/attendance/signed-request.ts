import type { SignedKey } from '@/lib/attendance/webauthn';
import { assertionPayload, requestOptions } from '@/lib/attendance/webauthn';

export type SignDeps = {
    fetcher: typeof fetch;
    /** `navigator.credentials.get()`, narrowed to a signature or null. */
    getKey: (
        options: PublicKeyCredentialRequestOptions,
    ) => Promise<SignedKey | null>;
    xsrf: string | null;
};

export async function post(
    url: string,
    body: unknown,
    { fetcher, xsrf }: SignDeps,
): Promise<Response> {
    return fetcher(url, {
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
async function sign(
    options: PublicKeyCredentialRequestOptions,
    deps: SignDeps,
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
    const response = await post(optionsUrl, {}, deps);

    if (!response.ok) {
        return response;
    }

    const options = requestOptions(await response.json());
    const key = options === null ? 'failed' : await sign(options, deps);

    return typeof key === 'string'
        ? key
        : post(url, { ...body, credential: assertionPayload(key) }, deps);
}
