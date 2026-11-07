import { describe, expect, it, vi } from 'vitest';
import { sendWeek, weekMessage, weekView } from '@/lib/attendance/week';
import type { WeekView } from '@/lib/attendance/week';
import type { SignedKey } from '@/lib/attendance/webauthn';

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

const week: WeekView = {
    name: 'Ana López',
    week: { start: '2026-11-02', end: '2026-11-08' },
    status: 'pending',
    answeredAt: null,
    disputeReason: null,
    days: [
        {
            date: '2026-11-02',
            marks: [
                {
                    type: 'start',
                    time: '09:00',
                    nextDay: false,
                    corrected: true,
                },
            ],
            workedMinutes: 540,
            scheduledMinutes: 480,
            restDay: false,
            missingEnd: false,
        },
    ],
    summary: {
        ordinaryMinutes: 480,
        overtimeMinutes: 60,
        doubleMinutes: 60,
        tripleMinutes: 0,
        restDayMinutes: 0,
        flags: ['day_over_12_hours'],
    },
    confirmUrl: '/a/acme/week/2026-11-02/confirm',
    disputeUrl: '/a/acme/week/2026-11-02/dispute',
    previousUrl: '/a/acme/week/2026-10-26',
    nextUrl: null,
};

function server(status = 200, body: unknown = week, optionsStatus = 200) {
    return vi.fn<typeof fetch>((url) =>
        Promise.resolve(
            url === '/options'
                ? new Response(JSON.stringify({ challenge: 'AQID' }), {
                      status: optionsStatus,
                  })
                : new Response(JSON.stringify(body), { status }),
        ),
    );
}

const signs = () => Promise.resolve(key);

describe('weekView', () => {
    it('reads the week the server sent', () => {
        expect(weekView(JSON.parse(JSON.stringify(week)))).toEqual(week);
    });

    it('is null for anything that is not a week', () => {
        expect(weekView(null)).toBeNull();
        expect(weekView({ ...week, name: 3 })).toBeNull();
        expect(weekView({ ...week, days: 'x' })).toBeNull();
        expect(weekView({ ...week, week: null })).toBeNull();
        expect(weekView({ ...week, summary: { flags: [] } })).toBeNull();
        expect(weekView({ ...week, previousUrl: null })).toBeNull();
    });

    it('leaves out the days, marks and flags it cannot read, and reads a missing text as none', () => {
        const read = weekView({
            ...week,
            answeredAt: 5,
            disputeReason: undefined,
            confirmUrl: 1,
            days: [
                ...week.days,
                { date: '2026-11-03' },
                {
                    ...week.days[0],
                    marks: [{ type: 'start' }, ...(week.days[0]?.marks ?? [])],
                },
            ],
            summary: { ...week.summary, flags: ['ok', 3] },
        });

        expect(read?.days).toHaveLength(2);
        expect(read?.days[1]?.marks).toHaveLength(1);
        expect(read?.summary.flags).toEqual(['ok']);
        expect(read?.answeredAt).toBeNull();
        expect(read?.disputeReason).toBeNull();
        expect(read?.confirmUrl).toBeNull();
    });
});

describe('sendWeek', () => {
    it('signs a challenge of the week and sends it with the body', async () => {
        const fetcher = server();

        await expect(
            sendWeek(
                {
                    optionsUrl: '/options',
                    url: '/week',
                    body: { start: '2026-11-02' },
                },
                {
                    fetcher,
                    getKey: signs,
                    xsrf: 'tok',
                },
            ),
        ).resolves.toEqual({ kind: 'done', week });

        const body = fetcher.mock.calls[1]?.[1]?.body;
        expect(typeof body === 'string' ? JSON.parse(body) : null).toEqual(
            expect.objectContaining({
                start: '2026-11-02',
                credential: expect.objectContaining({ id: 'CQ' }),
            }),
        );
    });

    it.each([
        [404, {}, 'not_verified'],
        [429, {}, 'throttled'],
        [422, { reason: 'week_not_over' }, 'week_not_over'],
        [422, { errors: { reason: ['x'] } }, 'invalid'],
        [500, {}, 'failed'],
        [200, { name: 'x' }, 'failed'],
    ])('tells why when the server answers %i', async (status, body, reason) => {
        await expect(
            sendWeek(
                { optionsUrl: '/options', url: '/week', body: {} },
                {
                    fetcher: server(status, body),
                    getKey: signs,
                    xsrf: null,
                },
            ),
        ).resolves.toEqual({ kind: 'failed', reason });
    });

    it('tells when the options failed or the person did not verify', async () => {
        await expect(
            sendWeek(
                { optionsUrl: '/options', url: '/week', body: {} },
                {
                    fetcher: server(200, week, 429),
                    getKey: signs,
                    xsrf: null,
                },
            ),
        ).resolves.toEqual({ kind: 'failed', reason: 'throttled' });

        await expect(
            sendWeek(
                { optionsUrl: '/options', url: '/week', body: {} },
                {
                    fetcher: server(),
                    getKey: () =>
                        Promise.reject(
                            new DOMException('no', 'NotAllowedError'),
                        ),
                    xsrf: null,
                },
            ),
        ).resolves.toEqual({ kind: 'failed', reason: 'cancelled' });
    });

    it('never throws', async () => {
        await expect(
            sendWeek(
                { optionsUrl: '/options', url: '/week', body: {} },
                {
                    fetcher: () => Promise.reject(new TypeError('offline')),
                    getKey: signs,
                    xsrf: null,
                },
            ),
        ).resolves.toEqual({ kind: 'failed', reason: 'failed' });
    });
});

describe('weekMessage', () => {
    it.each([
        ['not_verified', 'No se pudo verificar su dispositivo'],
        ['cancelled', 'No se confirmó su huella'],
        ['throttled', 'demasiados intentos'],
        ['week_not_over', 'cuando termine el domingo'],
        ['week_disputed', 'Recursos Humanos la revisará'],
        ['already_disputed', 'Ya objetó esta semana'],
        ['invalid', 'al menos 10 caracteres'],
        ['unsupported', 'llaves de acceso'],
        ['anything', 'Intente de nuevo'],
    ])('words %s', (reason, text) => {
        expect(weekMessage(reason)).toContain(text);
    });
});
