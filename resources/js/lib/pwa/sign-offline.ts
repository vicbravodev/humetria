import { sign } from '@/lib/attendance/signed-request';
import type { SignDeps } from '@/lib/attendance/signed-request';
import type { SignedKey } from '@/lib/webauthn';

/*
 * Signing a mark with the person's passkey without asking the server for a challenge: the challenge is the hash of the
 * canonical text (`challenge.ts`) and the server builds it again to verify. Nothing here touches the network.
 */

/** A refusal faster than this is the phone failing, not the person saying no. */
const QUICK_REFUSAL_MS = 2_000;

/** The ask of the device for a signature over `hash`, with the person verified, for the relying party the server uses. */
export function offlineOptions(
    hash: Uint8Array<ArrayBuffer>,
    rpId: string,
): PublicKeyCredentialRequestOptions {
    return {
        challenge: hash,
        rpId,
        userVerification: 'required',
        timeout: 60_000,
    };
}

/**
 * The signature of the device, or why there is none. A refusal in under two seconds is tried once more (an installed app
 * on Android was seen to refuse twice in 1.9 s and 1.3 s without the person touching anything); a second refusal, or a slow
 * one, is `cancelled` and the screen offers plan B. The only retry of the system, and only for this path.
 */
export async function signWithPasskey(
    options: PublicKeyCredentialRequestOptions,
    deps: Pick<SignDeps, 'getKey'> & { now: () => number },
): Promise<SignedKey | 'cancelled' | 'failed'> {
    const started = deps.now();
    const first = await sign(options, deps);

    return first === 'cancelled' && deps.now() - started < QUICK_REFUSAL_MS
        ? sign(options, deps)
        : first;
}
