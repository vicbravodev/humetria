import { toBase64url } from '@/lib/webauthn';

/*
 * The device key (plan B of the marks made without a connection): a key pair of this phone, ECDSA P-256, whose private half
 * can never leave it. It signs the 32 raw bytes of the challenge; the server verifies with the public half it registered
 * (WebCrypto and `openssl_verify` both hash that message with their own SHA-256).
 */

const SIGN: EcdsaParams = { name: 'ECDSA', hash: 'SHA-256' };

/** A new key pair: the private half to keep in the phone, and the public half as the base64url of its SPKI. */
export async function createDeviceKey(
    subtle: SubtleCrypto,
): Promise<{ privateKey: CryptoKey; spki: string }> {
    const { privateKey, publicKey } = await subtle.generateKey(
        { name: 'ECDSA', namedCurve: 'P-256' },
        false,
        ['sign', 'verify'],
    );

    return {
        privateKey,
        spki: toBase64url(await subtle.exportKey('spki', publicKey)),
    };
}

/** The signature of the bytes (r followed by s, 64 bytes) as base64url. */
export async function signBytes(
    subtle: SubtleCrypto,
    privateKey: CryptoKey,
    bytes: BufferSource,
): Promise<string> {
    return toBase64url(await subtle.sign(SIGN, privateKey, bytes));
}

/** Whether what the store gave back is a key (IndexedDB keeps whatever it was handed). */
export function isCryptoKey(value: unknown): value is CryptoKey {
    return typeof CryptoKey !== 'undefined' && value instanceof CryptoKey;
}
