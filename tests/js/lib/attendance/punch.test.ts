import { describe, expect, it, vi } from 'vitest';
import { punch, punchDay } from '@/lib/attendance/punch';
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
    name: 'Ana López',
    marks: [{ type: 'start', time: '09:00' }],
    next: ['break_start', 'end'],
    flag: null,
    evidence: 'strong',
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
                    markBody: {
                        reason: 'outside_zone',
                        name: 'Ana López',
                        next: ['start'],
                    },
                }),
                getKey: signs,
                xsrf: null,
            }),
        ).resolves.toEqual({
            kind: 'failed',
            reason: 'outside_zone',
            person: { name: 'Ana López', next: ['start'] },
        });
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
        ).resolves.toEqual({ kind: 'failed', reason, person: null });
    });

    it('fails when the options do not come', async () => {
        await expect(
            punch(request, {
                fetcher: serverAnswering({ optionsStatus: 429 }),
                getKey: signs,
                xsrf: null,
            }),
        ).resolves.toEqual({
            kind: 'failed',
            reason: 'throttled',
            person: null,
        });
        await expect(
            punch(request, {
                fetcher: serverAnswering({ optionsBody: { nope: 1 } }),
                getKey: signs,
                xsrf: null,
            }),
        ).resolves.toEqual({ kind: 'failed', reason: 'failed', person: null });
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
        ).resolves.toEqual({
            kind: 'failed',
            reason: 'cancelled',
            person: null,
        });
    });

    it('fails when the device gives no signature or another error', async () => {
        await expect(
            punch(request, {
                fetcher: serverAnswering(),
                getKey: () => Promise.resolve(null),
                xsrf: null,
            }),
        ).resolves.toEqual({ kind: 'failed', reason: 'failed', person: null });
        await expect(
            punch(request, {
                fetcher: serverAnswering(),
                getKey: () => Promise.reject(new Error('x')),
                xsrf: null,
            }),
        ).resolves.toEqual({ kind: 'failed', reason: 'failed', person: null });
    });

    it('fails when the network does', async () => {
        await expect(
            punch(request, {
                fetcher: () => Promise.reject(new TypeError('offline')),
                getKey: signs,
                xsrf: null,
            }),
        ).resolves.toEqual({ kind: 'failed', reason: 'failed', person: null });
    });
});

describe('punchDay', () => {
    it('reads how much the mark proves, and none when the server did not say', () => {
        expect(punchDay(day)?.evidence).toBe('strong');
        expect(punchDay({ ...day, evidence: undefined })?.evidence).toBeNull();
        expect(punchDay({ ...day, evidence: 3 })?.evidence).toBeNull();
    });

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
        ).toEqual({
            name: 'Ana López',
            marks: [],
            next: ['end'],
            flag: null,
            evidence: 'strong',
        });
        expect(punchDay({ name: 'Ana', marks: [], next: 'end' })).toBeNull();
        expect(punchDay({ marks: [], next: [] })).toBeNull();
        expect(punchDay(null)).toBeNull();
    });
});

describe('punch, telling who signed', () => {
    it('tells nobody when the server named nobody readable', async () => {
        await expect(
            punch(request, {
                fetcher: serverAnswering({
                    markStatus: 422,
                    markBody: { reason: 'day_not_open', name: 3, next: [] },
                }),
                getKey: signs,
                xsrf: null,
            }),
        ).resolves.toEqual({
            kind: 'failed',
            reason: 'day_not_open',
            person: null,
        });
    });
});
