import { describe, expect, it } from 'vitest';
import { punch, punchMessage } from '@/lib/attendance/punch';
import type { PunchRequest } from '@/lib/attendance/punch';
import type { SignedKey } from '@/lib/webauthn';

const key: SignedKey = {
    rawId: new Uint8Array([9]).buffer,
    type: 'public-key',
    response: {
        clientDataJSON: new Uint8Array([1]).buffer,
        authenticatorData: new Uint8Array([2]).buffer,
        signature: new Uint8Array([3]).buffer,
        userHandle: new Uint8Array([4]).buffer,
    },
};

const request: PunchRequest = {
    optionsUrl: '/me/acme/punch/options',
    punchUrl: '/me/acme/punch',
    type: 'start',
    qr: 'abc',
    position: { lat: 19.4, lng: -99.1, accuracy: 12 },
};

const failingWith = (error: unknown) => ({
    fetcher: () => Promise.reject(error),
    getKey: () => Promise.resolve(key),
    xsrf: null,
});

describe('punch, when there is no network', () => {
    it('says offline when the network fails or the server takes too long, so the app can keep the mark in the queue', async () => {
        for (const error of [
            new TypeError('Failed to fetch'),
            new DOMException('timed out', 'TimeoutError'),
            new DOMException('aborted', 'AbortError'),
        ]) {
            await expect(punch(request, failingWith(error))).resolves.toEqual({
                kind: 'failed',
                reason: 'offline',
                person: null,
            });
        }
    });

    it('fails, but not as offline, for any other error', async () => {
        await expect(
            punch(request, failingWith(new Error('boom'))),
        ).resolves.toEqual({ kind: 'failed', reason: 'failed', person: null });
    });

    it('tells a person with the mode off to try again with signal', () => {
        expect(punchMessage('offline')).toBe(
            'No hay internet. Intente de nuevo cuando tenga señal.',
        );
    });
});
