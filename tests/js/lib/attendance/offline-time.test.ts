import { describe, expect, it } from 'vitest';
import {
    clockOf,
    declaredFromTime,
    secondsOf,
} from '@/lib/attendance/offline-time';

/** A phone's wall clock: its own date and hour, whatever the zone of the machine that runs the test. */
const at = (hour: number, minute: number, day = 25) =>
    new Date(2026, 10, day, hour, minute, 30);

/** The same, on the dot of the minute: what a written HH:mm means. */
const sharp = (hour: number, minute: number, day = 25) =>
    new Date(2026, 10, day, hour, minute, 0);

describe('declaredFromTime', () => {
    it('is the hour the person wrote, of today on their phone, in unix seconds', () => {
        const now = at(9, 0);

        expect(declaredFromTime(now, '08:02')).toBe(secondsOf(sharp(8, 2)));
    });

    it('is of yesterday when that hour is still to come: a mark after midnight for the shift before', () => {
        const now = at(0, 30);
        const declared = declaredFromTime(now, '23:50');

        expect(declared).toBe(secondsOf(sharp(23, 50, 24)));
    });

    it('takes the hour as now when it is the minute that is running', () => {
        const now = at(9, 0);

        expect(declaredFromTime(now, '09:00')).toBe(secondsOf(sharp(9, 0)));
    });

    it('is nothing for text that is not an hour', () => {
        const now = at(9, 0);

        for (const text of ['', '8:2', '24:00', '12:60', 'ocho', '08:02:00']) {
            expect(declaredFromTime(now, text), text).toBeNull();
        }
    });
});

describe('clockOf', () => {
    it("writes the unix seconds as the phone's HH:mm", () => {
        expect(clockOf(secondsOf(at(8, 2)))).toBe('08:02');
        expect(clockOf(secondsOf(at(17, 45)))).toBe('17:45');
        expect(clockOf(secondsOf(at(0, 5)))).toBe('00:05');
    });
});

describe('secondsOf', () => {
    it('is the whole seconds of an instant', () => {
        expect(secondsOf(new Date(1_794_050_400_999))).toBe(1_794_050_400);
    });
});
