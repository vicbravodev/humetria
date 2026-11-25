import { describe, expect, it } from 'vitest';
import { weekView } from '@/lib/attendance/week';

const day = {
    date: '2026-11-04',
    marks: [],
    workedMinutes: 0,
    scheduledMinutes: 480,
    overtimeMinutes: 0,
};

const week = {
    name: 'Ana López',
    week: { start: '2026-11-02', end: '2026-11-08' },
    status: 'pending',
    digest: 'a'.repeat(64),
    blocked: 'uncovered_days',
    uncoveredDays: ['2026-11-04'],
    days: [{ ...day, uncovered: true }],
    requests: [],
    summary: {
        ordinaryMinutes: 0,
        overtimeMinutes: 0,
        doubleMinutes: 0,
        tripleMinutes: 0,
        restDayMinutes: 0,
        flags: [],
    },
    previousUrl: '/me/acme/week/2026-10-26',
};

describe('the days without cover in the week the server tells', () => {
    it('reads them for the week and for each day', () => {
        const read = weekView({
            ...week,
            uncoveredDays: ['2026-11-04', 5, '2026-11-05'],
            days: [day, { ...day, uncovered: true }],
        });

        expect(read).toMatchObject({
            blocked: 'uncovered_days',
            uncoveredDays: ['2026-11-04', '2026-11-05'],
        });
        expect(read?.days.map((each) => each.uncovered)).toEqual([false, true]);
    });

    it('reads anything that is not a list of dates, or a flag that is not true, as none', () => {
        expect(
            weekView({ ...week, uncoveredDays: 'x' })?.uncoveredDays,
        ).toEqual([]);
        expect(
            weekView({ ...week, uncoveredDays: undefined })?.uncoveredDays,
        ).toEqual([]);
        expect(
            weekView({ ...week, days: [{ ...day, uncovered: 'yes' }] })?.days[0]
                ?.uncovered,
        ).toBe(false);
    });
});
