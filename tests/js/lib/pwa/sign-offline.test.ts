import { describe, expect, it, vi } from 'vitest';
import { offlineOptions, signWithPasskey } from '@/lib/pwa/sign-offline';
import type { SignedKey } from '@/lib/webauthn';

const key: SignedKey = {
    rawId: new Uint8Array([9]).buffer,
    type: 'public-key',
    response: {
        clientDataJSON: new Uint8Array([1]).buffer,
        authenticatorData: new Uint8Array([2]).buffer,
        signature: new Uint8Array([3]).buffer,
        userHandle: null,
    },
};

const options = offlineOptions(new Uint8Array([7, 7]), 'humetria.test');
const refused = () => new DOMException('no', 'NotAllowedError');

/** A clock that gives these instants, one per call, and stays on the last. */
function clock(...instants: number[]) {
    let at = 0;

    return () => instants[Math.min(at++, instants.length - 1)] ?? 0;
}

/** A device that answers each ask with the next of these: a key, or an error it throws. */
function device(...answers: (SignedKey | Error)[]) {
    let asked = 0;

    return vi.fn<(o: PublicKeyCredentialRequestOptions) => Promise<SignedKey>>(
        () => {
            const answer = answers[Math.min(asked++, answers.length - 1)];

            return answer instanceof Error
                ? Promise.reject(answer)
                : Promise.resolve(answer ?? key);
        },
    );
}

describe('offlineOptions', () => {
    it('asks the device to sign the hash, with the person verified, for the relying party it was given', () => {
        expect(options).toEqual({
            challenge: new Uint8Array([7, 7]),
            rpId: 'humetria.test',
            userVerification: 'required',
            timeout: 60_000,
        });
    });
});

describe('signWithPasskey', () => {
    it('signs on the first try', async () => {
        const getKey = device(key);

        await expect(
            signWithPasskey(options, { getKey, now: clock(0) }),
        ).resolves.toBe(key);
        expect(getKey).toHaveBeenCalledTimes(1);
        expect(getKey).toHaveBeenCalledWith(options);
    });

    it('tries once more when the device refused fast, at 600 ms and at 1 999 ms', async () => {
        for (const elapsed of [600, 1_999]) {
            const getKey = device(refused(), key);

            await expect(
                signWithPasskey(options, {
                    getKey,
                    now: clock(1_000, 1_000 + elapsed),
                }),
            ).resolves.toBe(key);
            expect(getKey).toHaveBeenCalledTimes(2);
        }
    });

    it('does not try again when the refusal took 2 000 ms or 4 600 ms: the person said no', async () => {
        for (const elapsed of [2_000, 4_600]) {
            const getKey = device(refused(), key);

            await expect(
                signWithPasskey(options, {
                    getKey,
                    now: clock(1_000, 1_000 + elapsed),
                }),
            ).resolves.toBe('cancelled');
            expect(getKey).toHaveBeenCalledTimes(1);
        }
    });

    it('tries only once more: two fast refusals are cancelled', async () => {
        const getKey = device(refused(), refused(), key);

        await expect(
            signWithPasskey(options, { getKey, now: clock(0, 100, 200, 300) }),
        ).resolves.toBe('cancelled');
        expect(getKey).toHaveBeenCalledTimes(2);
    });

    it('says failed, without trying again, for any other error', async () => {
        const getKey = device(new Error('boom'), key);

        await expect(
            signWithPasskey(options, { getKey, now: clock(0, 100) }),
        ).resolves.toBe('failed');
        expect(getKey).toHaveBeenCalledTimes(1);
    });

    it('never goes to the network: it has no fetch to go with', async () => {
        const fetcher = vi.spyOn(globalThis, 'fetch');

        await signWithPasskey(options, {
            getKey: device(refused(), refused()),
            now: clock(0, 100),
        });

        expect(fetcher).not.toHaveBeenCalled();
        fetcher.mockRestore();
    });
});
