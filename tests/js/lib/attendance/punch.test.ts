import { describe, expect, it, vi } from 'vitest';
import {
    doneMessage,
    punch,
    punchDay,
    punchMessage,
} from '@/lib/attendance/punch';
import type { PunchRequest } from '@/lib/attendance/punch';
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

const day = {
    marks: [{ type: 'start', time: '09:00' }],
    next: ['break_start', 'end'],
    flag: null,
};

const request: PunchRequest = {
    optionsUrl: '/a/acme/punch/options',
    punchUrl: '/a/acme/punch',
    type: 'start',
    qr: 'abc',
    position: { lat: 19.4, lng: -99.1, accuracy: 12 },
};

/** A server that answers the options, then the mark, with these statuses and bodies. */
function serverAnswering({
    markStatus = 200,
    markBody = day,
    optionsStatus = 200,
    optionsBody = options,
}: {
    markStatus?: number;
    markBody?: unknown;
    optionsStatus?: number;
    optionsBody?: unknown;
} = {}) {
    return vi.fn<typeof fetch>((url) =>
        Promise.resolve(
            url === request.optionsUrl
                ? new Response(JSON.stringify(optionsBody), {
                      status: optionsStatus,
                  })
                : new Response(JSON.stringify(markBody), {
                      status: markStatus,
                  }),
        ),
    );
}

const signs = () => Promise.resolve(key);

/** The body of the mark the page sent, as JSON. */
function sentBody(fetcher: ReturnType<typeof serverAnswering>): unknown {
    const body = fetcher.mock.calls[1]?.[1]?.body;

    return typeof body === 'string' ? JSON.parse(body) : null;
}

describe('punch', () => {
    it('asks for the options, signs and sends the mark with the code and the position', async () => {
        const fetcher = serverAnswering();
        const getKey = vi.fn(signs);

        await expect(
            punch(request, { fetcher, getKey, xsrf: 'tok' }),
        ).resolves.toEqual({ kind: 'done', day });

        expect(getKey).toHaveBeenCalledWith(
            expect.objectContaining({ rpId: 'localhost' }),
        );
        const [url, init] = fetcher.mock.calls[1] ?? [];
        expect(url).toBe('/a/acme/punch');
        expect(init?.headers).toEqual({
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': 'tok',
        });
        expect(sentBody(fetcher)).toEqual({
            mark_type: 'start',
            q: 'abc',
            lat: 19.4,
            lng: -99.1,
            accuracy: 12,
            credential: expect.objectContaining({ id: 'CQ' }),
        });
    });

    it('sends no code and no position when there are none, and no token without its cookie', async () => {
        const fetcher = serverAnswering();

        await punch(
            { ...request, qr: null, position: null },
            { fetcher, getKey: signs, xsrf: null },
        );

        const init = fetcher.mock.calls[1]?.[1];
        expect(init?.headers).not.toHaveProperty('X-XSRF-TOKEN');
        expect(sentBody(fetcher)).not.toHaveProperty('lat');
        expect(sentBody(fetcher)).not.toHaveProperty('q');
    });
});

describe('punch, when the mark is not taken', () => {
    it('gives the reason the server rejected the mark with', async () => {
        await expect(
            punch(request, {
                fetcher: serverAnswering({
                    markStatus: 422,
                    markBody: { reason: 'outside_zone' },
                }),
                getKey: signs,
                xsrf: null,
            }),
        ).resolves.toEqual({ kind: 'failed', reason: 'outside_zone' });
    });

    it.each([
        [404, {}, 'not_verified'],
        [429, {}, 'throttled'],
        [422, { errors: {} }, 'failed'],
        [500, {}, 'failed'],
        [200, { marks: 'x' }, 'failed'],
    ])('reads a %i as %s', async (status, body, reason) => {
        await expect(
            punch(request, {
                fetcher: serverAnswering({
                    markStatus: status,
                    markBody: body,
                }),
                getKey: signs,
                xsrf: null,
            }),
        ).resolves.toEqual({ kind: 'failed', reason });
    });

    it('fails when the options do not come', async () => {
        await expect(
            punch(request, {
                fetcher: serverAnswering({ optionsStatus: 429 }),
                getKey: signs,
                xsrf: null,
            }),
        ).resolves.toEqual({ kind: 'failed', reason: 'throttled' });
        await expect(
            punch(request, {
                fetcher: serverAnswering({ optionsBody: { nope: 1 } }),
                getKey: signs,
                xsrf: null,
            }),
        ).resolves.toEqual({ kind: 'failed', reason: 'failed' });
    });

    it('is cancelled when the person closes the prompt or does not verify', async () => {
        const getKey = () =>
            Promise.reject(new DOMException('no', 'NotAllowedError'));

        await expect(
            punch(request, {
                fetcher: serverAnswering(),
                getKey,
                xsrf: null,
            }),
        ).resolves.toEqual({ kind: 'failed', reason: 'cancelled' });
    });

    it('fails when the device gives no signature or another error', async () => {
        await expect(
            punch(request, {
                fetcher: serverAnswering(),
                getKey: () => Promise.resolve(null),
                xsrf: null,
            }),
        ).resolves.toEqual({ kind: 'failed', reason: 'failed' });
        await expect(
            punch(request, {
                fetcher: serverAnswering(),
                getKey: () => Promise.reject(new Error('x')),
                xsrf: null,
            }),
        ).resolves.toEqual({ kind: 'failed', reason: 'failed' });
    });

    it('fails when the network does', async () => {
        await expect(
            punch(request, {
                fetcher: () => Promise.reject(new TypeError('offline')),
                getKey: signs,
                xsrf: null,
            }),
        ).resolves.toEqual({ kind: 'failed', reason: 'failed' });
    });
});

describe('punchDay', () => {
    it('reads the day the server sent', () => {
        expect(punchDay({ ...day, flag: 'outside_zone' })).toEqual({
            ...day,
            flag: 'outside_zone',
        });
    });

    it('leaves out marks it cannot read, and is null for anything else', () => {
        expect(
            punchDay({
                ...day,
                marks: [{ type: 'start' }, 3],
                next: [1, 'end'],
            }),
        ).toEqual({ marks: [], next: ['end'], flag: null });
        expect(punchDay({ marks: [], next: 'end' })).toBeNull();
        expect(punchDay(null)).toBeNull();
    });
});

describe('punchMessage', () => {
    it.each([
        'outside_zone',
        'accuracy_too_low',
        'no_location',
        'qr_required',
        'qr_invalid',
    ])('says what to do about %s, and to ask RH for a correction', (reason) => {
        expect(punchMessage(reason)).toContain('Recursos Humanos');
    });

    it.each([
        ['day_already_open', 'Ya marcó su entrada'],
        ['day_not_open', 'Primero marque su entrada'],
        ['already_on_break', 'Ya marcó su salida a comer'],
        ['not_on_break', 'Aún no marca su salida a comer'],
        ['not_verified', 'pida a Recursos Humanos'],
        ['cancelled', 'huella'],
        ['throttled', 'Espere un minuto'],
        ['unsupported', 'navegador'],
        ['failed', 'Intente de nuevo'],
        ['anything', 'Intente de nuevo'],
    ])('words %s', (reason, text) => {
        expect(punchMessage(reason)).toContain(text);
    });
});

describe('doneMessage', () => {
    it('confirms the mark with its time', () => {
        expect(doneMessage(day)).toBe(
            'Listo: se registró su entrada a las 09:00.',
        );
    });

    it('tells the person a mark out of zone is flagged for RH', () => {
        expect(doneMessage({ ...day, flag: 'outside_zone' })).toBe(
            'Listo: se registró su entrada a las 09:00. Quedó fuera de zona y Recursos Humanos la verá señalada.',
        );
    });

    it('tells the person a mark with no location is flagged for RH', () => {
        expect(doneMessage({ ...day, flag: 'no_location' })).toBe(
            'Listo: se registró su entrada a las 09:00. Quedó sin ubicación y Recursos Humanos la verá señalada.',
        );
    });

    it('says nothing of a flag only the panel reads, nor of a day with no marks', () => {
        expect(doneMessage({ ...day, flag: 'center_without_zones' })).toBe(
            'Listo: se registró su entrada a las 09:00.',
        );
        expect(doneMessage({ marks: [], next: [], flag: null })).toBe('Listo.');
    });
});

describe('doneMessage of each mark', () => {
    it.each([
        ['break_start', 'Listo: se registró su salida a comer a las 13:00.'],
        ['break_end', 'Listo: se registró su regreso de comer a las 13:00.'],
        ['end', 'Listo: se registró su salida a las 13:00.'],
    ])('names %s', (type, text) => {
        expect(
            doneMessage({
                marks: [{ type, time: '13:00' }],
                next: [],
                flag: null,
            }),
        ).toBe(text);
    });
});
