import { describe, expect, it } from 'vite-plus/test';

import {
    hoursText,
    minutesOf,
    modeLabel,
    overMaximum,
    scheduleDays,
    shiftLabel,
    weekTotal,
} from '@/lib/attendance/schedule';

const week = (...days: number[]) =>
    Object.fromEntries(days.map((minutes, at) => [String(at + 1), minutes]));

describe('a schedule in a row', () => {
    it('groups the days in a row with the same hours', () => {
        expect(scheduleDays(week(480, 480, 480, 480, 480, 0, 0))).toBe(
            'L–V 8 h',
        );
        expect(scheduleDays(week(420, 420, 420, 420, 420, 420, 0))).toBe(
            'L–S 7 h',
        );
        expect(scheduleDays(week(480, 480, 480, 480, 480, 240, 0))).toBe(
            'L–V 8 h · S 4 h',
        );
        expect(scheduleDays(week(450, 0, 450, 0, 450, 0, 0))).toBe(
            'L 7 h 30 min · X 7 h 30 min · V 7 h 30 min',
        );
        expect(scheduleDays(week(0, 0, 0, 0, 0, 480, 480))).toBe('S–D 8 h');
    });

    it('says when it has no working day', () => {
        expect(scheduleDays(week(0, 0, 0, 0, 0, 0, 0))).toBe(
            'Sin días de trabajo',
        );
    });

    it('names the shift and where the person works', () => {
        expect(shiftLabel('day')).toBe('Diurna');
        expect(shiftLabel('night')).toBe('Nocturna');
        expect(shiftLabel('mixed')).toBe('Mixta');
        expect(modeLabel('onsite')).toBe('Presencial');
        expect(modeLabel('remote')).toBe('Remoto');
    });
});

describe('the hours of the week', () => {
    it('adds the seven days, a day not written as 0', () => {
        expect(weekTotal(week(480, 480, 480, 480, 480, 0, 0))).toBe(2400);
        expect(weekTotal({ '1': 60 })).toBe(60);
    });

    it("warns gently above the year's ordinary maximum, never at it", () => {
        expect(overMaximum(2880, { year: 2026, minutes: 2880 })).toBeNull();
        expect(overMaximum(3000, { year: 2026, minutes: 2880 })).toBe(
            'Son 2 h más que el máximo ordinario de 48 h a la semana en 2026: lo que pase de ese máximo cuenta como tiempo extra.',
        );
    });
});

describe('the hours of a day as RH writes them', () => {
    it('reads hours with a point or a comma as minutes', () => {
        expect(minutesOf('8')).toBe(480);
        expect(minutesOf('7.5')).toBe(450);
        expect(minutesOf('7,25')).toBe(435);
        expect(minutesOf(' 0 ')).toBe(0);
    });

    it('leaves out what is not a number, for the server to say so', () => {
        expect(minutesOf('')).toBeNull();
        expect(minutesOf('ocho')).toBeNull();
    });

    it('writes minutes as hours to edit', () => {
        expect(hoursText(480)).toBe('8');
        expect(hoursText(450)).toBe('7.5');
        expect(hoursText(0)).toBe('0');
    });
});
