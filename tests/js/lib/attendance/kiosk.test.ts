import { describe, expect, it } from 'vitest';
import {
    fetchKioskCode,
    parseKioskCode,
    refreshDelay,
    retryDelay,
} from '@/lib/attendance/kiosk';

describe('refreshDelay', () => {
    it('asks for the next code half a second after the window turns', () => {
        expect(refreshDelay(20)).toBe(20_500);
        expect(refreshDelay(30)).toBe(30_500);
    });

    it('keeps between one and thirty seconds, whatever the server says', () => {
        expect(refreshDelay(0)).toBe(1_500);
        expect(refreshDelay(-4)).toBe(1_500);
        expect(refreshDelay(300)).toBe(30_500);
    });
});

describe('retryDelay', () => {
    it('doubles from one second on each failure, up to thirty', () => {
        expect([1, 2, 3, 4, 5, 6, 7].map(retryDelay)).toEqual([
            1_000, 2_000, 4_000, 8_000, 16_000, 30_000, 30_000,
        ]);
    });
});

describe('parseKioskCode', () => {
    const body = {
        token: 'abc',
        url: 'https://humetria.test/a/acme/punch?q=abc',
        qr: 'data:image/svg+xml;base64,PHN2Zz4=',
        expiresAt: '2026-11-07T12:00:30+00:00',
        expiresIn: 20,
    };

    it('reads the code the server sent', () => {
        expect(parseKioskCode(body)).toEqual({
            url: body.url,
            qr: body.qr,
            expiresIn: 20,
        });
    });

    it('gives nothing for a body that is not a code', () => {
        expect(parseKioskCode(null)).toBeNull();
        expect(parseKioskCode('x')).toBeNull();
        expect(parseKioskCode({ ...body, qr: 1 })).toBeNull();
        expect(parseKioskCode({ ...body, url: null })).toBeNull();
        expect(parseKioskCode({ ...body, expiresIn: '20' })).toBeNull();
        expect(
            parseKioskCode({ ...body, qr: 'javascript:alert(1)' }),
        ).toBeNull();
    });
});

describe('fetchKioskCode', () => {
    const code = {
        url: 'https://humetria.test/a/acme/punch?q=abc',
        qr: 'data:image/svg+xml;base64,PHN2Zz4=',
        expiresIn: 20,
    };
    const answering = (response: Response | Error) => (): Promise<Response> =>
        response instanceof Error
            ? Promise.reject(response)
            : Promise.resolve(response);

    it('reads the code of a good answer, asking as JSON with the cookie and no cache', async () => {
        const calls: Array<[RequestInfo | URL, RequestInit | undefined]> = [];
        const fetcher = (input: RequestInfo | URL, init?: RequestInit) => {
            calls.push([input, init]);

            return Promise.resolve(Response.json(code));
        };

        expect(await fetchKioskCode('/a/acme/qr', fetcher)).toEqual(code);
        expect(calls).toEqual([
            [
                '/a/acme/qr',
                {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                    cache: 'no-store',
                },
            ],
        ]);
    });

    it('says the screen is gone on a 404', async () => {
        expect(
            await fetchKioskCode(
                '/qr',
                answering(new Response('', { status: 404 })),
            ),
        ).toBe('gone');
    });

    it('gives nothing, never throwing, for a network error, an error status or a page that is not JSON', async () => {
        expect(
            await fetchKioskCode('/qr', answering(new TypeError('offline'))),
        ).toBeNull();
        expect(
            await fetchKioskCode(
                '/qr',
                answering(Response.json(code, { status: 500 })),
            ),
        ).toBeNull();
        expect(
            await fetchKioskCode(
                '/qr',
                answering(new Response('<html>Wi-Fi</html>', { status: 200 })),
            ),
        ).toBeNull();
        expect(
            await fetchKioskCode('/qr', answering(Response.json({ url: 1 }))),
        ).toBeNull();
    });
});
