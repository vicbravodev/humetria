import { describe, expect, it } from 'vitest';
import {
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
