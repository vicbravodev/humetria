import { describe, expect, it } from 'vitest';
import { lateCount, lateLabel } from '@/lib/attendance/late';
import { minutesOfTime, timeOfMinutes } from '@/lib/attendance/schedule';

describe('lateLabel', () => {
    it('says nothing for a day with no lateness or no start time to compare', () => {
        expect(lateLabel(null)).toBeNull();
        expect(lateLabel(0)).toBeNull();
    });

    it('says the minutes, or the hours and minutes', () => {
        expect(lateLabel(11)).toBe('Retardo de 11 min');
        expect(lateLabel(60)).toBe('Retardo de 1 h');
        expect(lateLabel(90)).toBe('Retardo de 1 h 30 min');
    });
});

describe('lateCount', () => {
    it('counts the days with lateness and words it in the singular or plural', () => {
        expect(
            lateCount([{ lateMinutes: null }, { lateMinutes: 0 }]),
        ).toBeNull();
        expect(lateCount([{ lateMinutes: 5 }, {}, { lateMinutes: 0 }])).toBe(
            '1 retardo',
        );
        expect(lateCount([{ lateMinutes: 5 }, { lateMinutes: 90 }])).toBe(
            '2 retardos',
        );
    });
});

describe('start times of a schedule', () => {
    it('reads the time of a field as minutes from midnight, and an empty or odd one as none', () => {
        expect(minutesOfTime('09:00')).toBe(540);
        expect(minutesOfTime('00:00')).toBe(0);
        expect(minutesOfTime('23:59')).toBe(1439);
        expect(minutesOfTime('')).toBeNull();
        expect(minutesOfTime('9')).toBeNull();
        expect(minutesOfTime('24:00')).toBeNull();
        expect(minutesOfTime('09:60')).toBeNull();
    });

    it('writes minutes from midnight as the time a field takes', () => {
        expect(timeOfMinutes(540)).toBe('09:00');
        expect(timeOfMinutes(0)).toBe('00:00');
        expect(timeOfMinutes(1439)).toBe('23:59');
        expect(timeOfMinutes(null)).toBe('');
        expect(timeOfMinutes(undefined)).toBe('');
    });
});
