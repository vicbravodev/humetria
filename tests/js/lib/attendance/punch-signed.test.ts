import { describe, expect, it, vi } from 'vitest';
import { punch, punchDay } from '@/lib/attendance/punch';
import type { PunchRequest } from '@/lib/attendance/punch';
import type { SignedKey } from '@/lib/webauthn';

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

describe('punchDay, the date and the clock of each mark', () => {
    it('reads the day of each mark and the zone of one taken elsewhere, as the server sent them', () => {
        expect(
            punchDay({
                ...day,
                marks: [
                    {
                        type: 'start',
                        time: '23:59',
                        date: '2026-11-09',
                        clock: null,
                    },
                    {
                        type: 'end',
                        time: '00:01',
                        date: '2026-11-10',
                        clock: 'America/Tijuana',
                    },
                ],
            })?.marks,
        ).toEqual([
            { type: 'start', time: '23:59', date: '2026-11-09' },
            {
                type: 'end',
                time: '00:01',
                date: '2026-11-10',
                clock: 'America/Tijuana',
            },
        ]);
    });
});

describe('punch, accepting the agreement with the mark', () => {
    it('sends the version read with the mark, for one signature to accept and mark', async () => {
        const fetcher = serverAnswering();

        await punch(
            { ...request, agreementVersion: 3 },
            { fetcher, getKey: signs, xsrf: null },
        );

        expect(sentBody(fetcher)).toMatchObject({
            mark_type: 'start',
            agreement_version: 3,
        });
    });

    it('sends no version when none was read', async () => {
        const fetcher = serverAnswering();

        await punch(request, { fetcher, getKey: signs, xsrf: null });

        expect(sentBody(fetcher)).not.toHaveProperty('agreement_version');
    });
});

describe('punch, a code of another center', () => {
    it('gives the center whose code it was, for the screen to send the person to theirs', async () => {
        await expect(
            punch(request, {
                fetcher: serverAnswering({
                    markStatus: 422,
                    markBody: {
                        reason: 'qr_other_center',
                        name: 'Ana López',
                        next: ['start'],
                        center: 'Oficinas Querétaro',
                    },
                }),
                getKey: signs,
                xsrf: null,
            }),
        ).resolves.toStrictEqual({
            kind: 'failed',
            reason: 'qr_other_center',
            person: { name: 'Ana López', next: ['start'] },
            center: 'Oficinas Querétaro',
        });
    });
});
