import { describe, expect, it } from 'vitest';
import {
    calendarQuery,
    daysOfMonth,
    isRestDay,
    monthLabel,
    stretchColumns,
    weekdayInitial,
} from '@/lib/leave/calendar';

describe('daysOfMonth', () => {
    it('lists the days of the month with their weekday, Monday first', () => {
        const days = daysOfMonth('2026-11');

        expect(days).toHaveLength(30);
        expect(days[0]).toEqual({ date: '2026-11-01', day: 1, weekday: 6 });
        expect(days[1]).toEqual({ date: '2026-11-02', day: 2, weekday: 0 });
        expect(days[29]).toEqual({ date: '2026-11-30', day: 30, weekday: 0 });
    });

    it('knows the length of February in leap and common years', () => {
        expect(daysOfMonth('2028-02')).toHaveLength(29);
        expect(daysOfMonth('2027-02')).toHaveLength(28);
        expect(daysOfMonth('2026-12')).toHaveLength(31);
    });

    it('lists nothing for what is not a month', () => {
        expect(daysOfMonth('noviembre')).toEqual([]);
        expect(daysOfMonth('2026-13')).toEqual([]);
    });
});

describe('stretchColumns', () => {
    it('puts a stretch of three days in its three columns', () => {
        expect(stretchColumns('2026-11-10', '2026-11-12')).toEqual({
            column: 10,
            span: 3,
        });
    });

    it('takes one column for a single day and starts at the first for a stretch cut at the start', () => {
        expect(stretchColumns('2026-11-05', '2026-11-05')).toEqual({
            column: 5,
            span: 1,
        });
        expect(stretchColumns('2026-11-01', '2026-11-03')).toEqual({
            column: 1,
            span: 3,
        });
    });

    it('reaches the last day of the month', () => {
        expect(stretchColumns('2026-11-29', '2026-11-30')).toEqual({
            column: 29,
            span: 2,
        });
    });
});

describe('weekdayInitial and monthLabel', () => {
    it('names the weekday by its initial and the month in Spanish', () => {
        expect(weekdayInitial(0)).toBe('L');
        expect(weekdayInitial(6)).toBe('D');
        expect(weekdayInitial(9)).toBe('');
        expect(monthLabel('2026-11')).toBe('noviembre de 2026');
        expect(monthLabel('x')).toBe('');
    });
});

describe('calendarQuery', () => {
    it('keeps the month and only the filters that narrow it', () => {
        const none = { center: null, area: null };

        expect(calendarQuery('2026-11', none)).toEqual({ month: '2026-11' });
        expect(
            calendarQuery('2026-12', {
                center: { value: 'c1', label: 'Planta' },
                area: { value: 'a1', label: 'Ventas' },
            }),
        ).toEqual({ month: '2026-12', work_center: 'c1', area: 'a1' });
    });
});

describe('isRestDay', () => {
    const sunday = { date: '2026-11-01', day: 1, weekday: 6 };
    const monday = { date: '2026-11-02', day: 2, weekday: 0 };
    const revolution = { date: '2026-11-16', day: 16, weekday: 0 };

    it('rests on weekends and on the holidays it is told', () => {
        expect(isRestDay(sunday, [])).toBe(true);
        expect(isRestDay(monday, [])).toBe(false);
        expect(isRestDay(revolution, [])).toBe(false);
        expect(isRestDay(revolution, ['2026-11-16'])).toBe(true);
    });
});
