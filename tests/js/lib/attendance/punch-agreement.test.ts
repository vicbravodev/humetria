import { describe, expect, it, vi } from 'vitest';
import {
    acceptAgreement,
    agreementMessage,
    agreementOf,
} from '@/lib/attendance/punch-agreement';
import type { AcceptRequest } from '@/lib/attendance/punch-agreement';
import type { SignedKey } from '@/lib/attendance/webauthn';

const options = { challenge: 'AQID', rpId: 'localhost' };

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

const request: AcceptRequest = {
    optionsUrl: '/a/acme/agreement/options',
    url: '/a/acme/agreement',
    version: 2,
};

/** A server that answers the options, then the acceptance, with this status and body. */
function serverAnswering(status: number, body: unknown) {
    return vi.fn<typeof fetch>((url) =>
        Promise.resolve(
            url === request.optionsUrl
                ? new Response(JSON.stringify(options), { status: 200 })
                : new Response(JSON.stringify(body), { status }),
        ),
    );
}

const signs = () => Promise.resolve(key);

describe('agreementOf', () => {
    it('reads the agreement of an answer', () => {
        expect(
            agreementOf({ agreement: { version: 3, body: 'Texto' } }),
        ).toEqual({ version: 3, body: 'Texto' });
    });

    it('is null when the answer brings none it can read', () => {
        expect(agreementOf({ reason: 'outside_zone' })).toBeNull();
        expect(
            agreementOf({ agreement: { version: '3', body: 'x' } }),
        ).toBeNull();
        expect(agreementOf(null)).toBeNull();
    });
});

describe('acceptAgreement', () => {
    it('signs and sends the version read, and says it was accepted', async () => {
        const fetcher = serverAnswering(200, { accepted: true });

        await expect(
            acceptAgreement(request, { fetcher, getKey: signs, xsrf: 'tok' }),
        ).resolves.toEqual({ kind: 'accepted' });

        const body = fetcher.mock.calls[1]?.[1]?.body;
        expect(typeof body === 'string' ? JSON.parse(body) : null).toEqual(
            expect.objectContaining({ agreement_version: 2 }),
        );
    });

    it('gives the new text when the version read is no longer the current one', async () => {
        const fetcher = serverAnswering(409, {
            reason: 'agreement_outdated',
            agreement: { version: 3, body: 'Nuevo' },
        });

        await expect(
            acceptAgreement(request, { fetcher, getKey: signs, xsrf: null }),
        ).resolves.toEqual({
            kind: 'outdated',
            agreement: { version: 3, body: 'Nuevo' },
        });
    });

    it('says the signature was cancelled and sends nothing', async () => {
        const fetcher = serverAnswering(200, { accepted: true });
        const cancel = () =>
            Promise.reject(new DOMException('no', 'NotAllowedError'));

        await expect(
            acceptAgreement(request, { fetcher, getKey: cancel, xsrf: null }),
        ).resolves.toEqual({ kind: 'failed', reason: 'cancelled' });
        expect(fetcher).toHaveBeenCalledTimes(1);
    });

    it('says the key was not verified or the tries were too many', async () => {
        await expect(
            acceptAgreement(request, {
                fetcher: serverAnswering(404, {}),
                getKey: signs,
                xsrf: null,
            }),
        ).resolves.toEqual({ kind: 'failed', reason: 'not_verified' });
        await expect(
            acceptAgreement(request, {
                fetcher: serverAnswering(429, {}),
                getKey: signs,
                xsrf: null,
            }),
        ).resolves.toEqual({ kind: 'failed', reason: 'throttled' });
    });

    it('never throws with no signal', async () => {
        const fetcher = vi.fn<typeof fetch>(() =>
            Promise.reject(new TypeError('offline')),
        );

        await expect(
            acceptAgreement(request, { fetcher, getKey: signs, xsrf: null }),
        ).resolves.toEqual({ kind: 'failed', reason: 'failed' });
    });

    it('fails when a 409 brings no text to read', async () => {
        await expect(
            acceptAgreement(request, {
                fetcher: serverAnswering(409, {}),
                getKey: signs,
                xsrf: null,
            }),
        ).resolves.toEqual({ kind: 'failed', reason: 'failed' });
    });
});

describe('agreementMessage', () => {
    it('says what to do, in «usted»', () => {
        expect(agreementMessage('outdated')).toBe(
            'El acuerdo cambió. Lea la versión nueva y acéptela para marcar.',
        );
        expect(agreementMessage('cancelled')).toBe(
            'No se confirmó su huella, su rostro o su PIN. Intente de nuevo.',
        );
        expect(agreementMessage('throttled')).toBe(
            'Hubo demasiados intentos. Espere un minuto e intente de nuevo.',
        );
        expect(agreementMessage('failed')).toBe(
            'No se pudo enviar su aceptación. Revise su conexión e intente de nuevo.',
        );
    });
});
