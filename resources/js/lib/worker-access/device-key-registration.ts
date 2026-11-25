import { createDeviceKey, signBytes } from '@/lib/pwa/device-key';
import { passIn } from '@/lib/pwa/tickets';
import { fromBase64url } from '@/lib/webauthn';
import { postJson } from '@/lib/worker-access/http';
import type { HttpDeps } from '@/lib/worker-access/http';

/** What registering this phone's device key (plan B of the marks without a connection) came to. */
export type DeviceKeyRegistration =
    | {
          kind: 'ready';
          key: CryptoKey;
          deviceKeyId: string;
          pass: string;
          passId: string;
          passExpiresAt: string;
      }
    /** The server wants a recent proof of who the person is first. */
    | { kind: 'proof' }
    /** The person's session ended. */
    | { kind: 'session' }
    | { kind: 'failed' };

type Refusal = Exclude<DeviceKeyRegistration, { kind: 'ready' }>;

/** What a refused answer means: a recent proof is asked (403 with `proofRequired`), the session ended (401), or anything else. */
async function refusal(response: Response): Promise<Refusal> {
    if (response.status === 401) {
        return { kind: 'session' };
    }

    if (response.status !== 403) {
        return { kind: 'failed' };
    }

    const json: unknown = await response.json();

    return typeof json === 'object' &&
        json !== null &&
        'proofRequired' in json &&
        json.proofRequired === true
        ? { kind: 'proof' }
        : { kind: 'failed' };
}

/** The server's challenge as text and as the 32 bytes that are signed; null when it is not one. */
async function challengeOf(
    response: Response,
): Promise<{ text: string; bytes: Uint8Array<ArrayBuffer> } | null> {
    const json: unknown = await response.json();

    return typeof json === 'object' &&
        json !== null &&
        'challenge' in json &&
        typeof json.challenge === 'string'
        ? { text: json.challenge, bytes: fromBase64url(json.challenge) }
        : null;
}

/**
 * Registers a new device key: the server gives a challenge, the phone makes a P-256 key that cannot be exported and signs the
 * 32 raw bytes of the challenge with it (the proof it has the key), and the server answers with a pass of 24 hours. Only
 * JSON, with the person's session and CSRF token. Never throws.
 */
export async function registerDeviceKey(
    urls: { optionsUrl: string; storeUrl: string },
    deps: HttpDeps & { subtle: SubtleCrypto; label: string },
): Promise<DeviceKeyRegistration> {
    try {
        const asked = await postJson(urls.optionsUrl, {}, deps);

        if (!asked.ok) {
            return await refusal(asked);
        }

        const challenge = await challengeOf(asked);

        if (challenge === null) {
            return { kind: 'failed' };
        }

        const { privateKey, spki } = await createDeviceKey(deps.subtle);
        const stored = await postJson(
            urls.storeUrl,
            {
                public_key: spki,
                challenge: challenge.text,
                signature: await signBytes(
                    deps.subtle,
                    privateKey,
                    challenge.bytes,
                ),
                label: deps.label,
            },
            deps,
        );

        if (stored.status !== 201) {
            return await refusal(stored);
        }

        const pass = passIn(await stored.json());

        return pass === null
            ? { kind: 'failed' }
            : { kind: 'ready', key: privateKey, ...pass };
    } catch {
        return { kind: 'failed' };
    }
}
