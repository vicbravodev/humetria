import { describe, expect, it } from 'vite-plus/test';

import { sharedStart, startLabel } from '@/lib/attendance/schedule-starts';

const week = (...days: number[]) =>
    Object.fromEntries(days.map((minutes, at) => [String(at + 1), minutes]));

describe('the start time of a schedule', () => {
    const days = week(480, 480, 480, 480, 480, 0, 0);

    it('says the one time every working day starts at', () => {
        expect(
            startLabel(days, { 1: 540, 2: 540, 3: 540, 4: 540, 5: 540 }),
        ).toBe('Entrada 09:00');
    });

    it('says the range when the days start at different times, ignoring rest days', () => {
        expect(
            startLabel(days, {
                1: 480,
                2: 540,
                3: 480,
                4: 480,
                5: 570,
                7: 600,
            }),
        ).toBe('Entradas de 08:00 a 09:30');
    });

    it('says nothing when no day has a start time', () => {
        expect(startLabel(days, null)).toBeNull();
        expect(startLabel(days, { 1: null, 2: null })).toBeNull();
    });
});

describe('the start Monday to Friday share', () => {
    it('is the time of the five days when they agree', () => {
        expect(
            sharedStart({
                1: '09:00',
                2: '09:00',
                3: '09:00',
                4: '09:00',
                5: '09:00',
                6: '',
                7: '',
            }),
        ).toBe('09:00');
    });

    it('is empty when any of the five differs, or none is set', () => {
        expect(
            sharedStart({
                1: '09:00',
                2: '09:00',
                3: '08:00',
                4: '09:00',
                5: '09:00',
            }),
        ).toBe('');
        expect(sharedStart({})).toBe('');
    });
});
