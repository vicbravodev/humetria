import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { createDeviceKey, isCryptoKey, signBytes } from '@/lib/pwa/device-key';
import { fromBase64url } from '@/lib/webauthn';

const { subtle } = crypto;

type Vector = {
    name: string;
    spki: string;
    message: string;
    signature: string;
    valid: boolean;
};

/** The vectors PHP checks too: tests/Unit/WorkerAccess/DeviceKeyCryptoTest.php. */
const vectors: Vector[] = JSON.parse(
    readFileSync(
        'tests/Fixtures/worker-access/device-key-vectors.json',
        'utf8',
    ),
).vectors;

const ECDSA: EcdsaParams = { name: 'ECDSA', hash: 'SHA-256' };

function bytesOf(hex: string): Uint8Array<ArrayBuffer> {
    return Uint8Array.from(hex.match(/../g) ?? [], (pair) =>
        Number.parseInt(pair, 16),
    );
}

async function verifies(
    spki: string,
    signature: BufferSource,
    message: BufferSource,
) {
    const key = await subtle.importKey(
        'spki',
        fromBase64url(spki),
        { name: 'ECDSA', namedCurve: 'P-256' },
        false,
        ['verify'],
    );

    return subtle.verify(ECDSA, key, signature, message);
}

describe('createDeviceKey', () => {
    it('makes a key whose private half cannot be exported', async () => {
        const { privateKey } = await createDeviceKey(subtle);

        await expect(subtle.exportKey('pkcs8', privateKey)).rejects.toThrow();
    });

    it('gives the public half as the base64url of its SPKI', async () => {
        const { spki } = await createDeviceKey(subtle);

        expect(spki).toMatch(/^[A-Za-z0-9_-]+$/);
        // The SPKI of a P-256 key is 91 bytes and starts with the DER sequence.
        expect(fromBase64url(spki)).toHaveLength(91);
        expect(fromBase64url(spki)[0]).toBe(0x30);
    });
});

describe('signBytes', () => {
    it('signs 32 bytes in 64 bytes (r and s) that the public key verifies', async () => {
        const { privateKey, spki } = await createDeviceKey(subtle);
        const message = crypto.getRandomValues(new Uint8Array(32));
        const signature = await signBytes(subtle, privateKey, message);

        expect(fromBase64url(signature)).toHaveLength(64);
        expect(await verifies(spki, fromBase64url(signature), message)).toBe(
            true,
        );
        expect(
            await verifies(
                spki,
                fromBase64url(signature),
                crypto.getRandomValues(new Uint8Array(32)),
            ),
        ).toBe(false);
    });
});

describe('the shared vectors', () => {
    it('verify, or not, as PHP says', async () => {
        expect(vectors.length).toBeGreaterThanOrEqual(3);

        for (const vector of vectors) {
            expect(
                await verifies(
                    vector.spki,
                    fromBase64url(vector.signature),
                    bytesOf(vector.message),
                ),
                vector.name,
            ).toBe(vector.valid);
        }
    });
});

describe('isCryptoKey', () => {
    it('knows a key from anything the store could hold', async () => {
        const { privateKey } = await createDeviceKey(subtle);

        expect(isCryptoKey(privateKey)).toBe(true);
        expect(isCryptoKey(null)).toBe(false);
        expect(isCryptoKey({ type: 'private' })).toBe(false);
        expect(isCryptoKey('key')).toBe(false);
    });
});
