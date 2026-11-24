import { describe, expect, it } from 'vitest';
import {
    dayNeedsLook,
    dayRange,
    dayShort,
    weekRange,
} from '@/lib/attendance/week-fold';
import {
    absenceLabel,
    ribbonDays,
    weekActions,
    weekTotal,
} from '@/lib/attendance/week-text';
import type { WeekDay, WeekView } from '@/lib/attendance/week';

const day: WeekDay = {
    date: '2026-11-02',
    marks: [],
    workedMinutes: 480,
    scheduledMinutes: 480,
    overtimeMinutes: 0,
    restDay: false,
    missingEnd: false,
};

const week: WeekView = {
    name: 'Ana López',
    week: { start: '2026-11-02', end: '2026-11-08' },
    status: 'pending',
    answeredAt: null,
    disputeReason: null,
    resolution: null,
    digest: 'a'.repeat(64),
    blocked: null,
    days: [day],
    requests: [],
    summary: {
        ordinaryMinutes: 480,
        overtimeMinutes: 0,
        doubleMinutes: 0,
        tripleMinutes: 0,
        restDayMinutes: 0,
        flags: [],
    },
    confirmUrl: null,
    disputeUrl: null,
    previousUrl: '/me/acme/week/2026-10-26',
    nextUrl: null,
};

describe('weekRange', () => {
    it('names the week in the bar between its arrows, short (hallazgo 21)', () => {
        expect(weekRange('2026-11-09', '2026-11-15')).toBe('9 a 15 nov 2026');
        expect(weekRange('2026-11-30', '2026-12-06')).toBe(
            '30 nov a 6 dic 2026',
        );
        expect(weekRange('2026-12-28', '2027-01-03')).toBe(
            '28 dic 2026 a 3 ene 2027',
        );
    });
});

describe('weekTotal', () => {
    it('adds the hours worked, in ordinary and extra, and details only the kinds of extra there are', () => {
        const worked = {
            ...week,
            days: [
                { ...day, workedMinutes: 540, overtimeMinutes: 60 },
                { ...day, workedMinutes: 480 },
            ],
            summary: {
                ...week.summary,
                ordinaryMinutes: 960,
                overtimeMinutes: 60,
                doubleMinutes: 60,
            },
        };

        expect(weekTotal(worked)).toEqual({
            total: '17 h',
            lines: ['16 h ordinarias', '1 h extra'],
            detail: '1 h dobles',
        });
    });

    it('names the triples and the rest day only when there are', () => {
        expect(
            weekTotal({
                ...week,
                days: [{ ...day, workedMinutes: 600 }],
                summary: {
                    ...week.summary,
                    ordinaryMinutes: 480,
                    overtimeMinutes: 90,
                    doubleMinutes: 60,
                    tripleMinutes: 30,
                    restDayMinutes: 30,
                },
            }).detail,
        ).toBe('1 h dobles · 30 min triples · 30 min en descanso');
    });

    it('says «0 h extra» and no detail for a week with none', () => {
        expect(weekTotal(week)).toEqual({
            total: '8 h',
            lines: ['8 h ordinarias', '0 h extra'],
            detail: null,
        });
    });

    it('says there are no hours for a week without marks', () => {
        expect(
            weekTotal({ ...week, days: [{ ...day, workedMinutes: 0 }] }),
        ).toEqual({
            total: '0 h',
            lines: ['Sin horas trabajadas'],
            detail: null,
        });
    });
});

describe('ribbonDays', () => {
    it('gives the bar of the week the minutes of each day and its overtime', () => {
        expect(
            ribbonDays([
                { ...day, workedMinutes: 540, overtimeMinutes: 60 },
                { ...day, workedMinutes: 0 },
            ]),
        ).toEqual([
            { worked: 540, overtime: 60 },
            { worked: 0, overtime: 0 },
        ]);
    });
});

describe('a day away', () => {
    it('marks on the bar of the week the days of vacations or leave', () => {
        expect(
            ribbonDays([
                { ...day, workedMinutes: 0, absence: 'vacation' },
                { ...day, workedMinutes: 240, absence: 'leave' },
                { ...day, absence: null },
            ]),
        ).toEqual([
            { worked: 0, overtime: 0, absent: true },
            { worked: 240, overtime: 0, absent: true },
            { worked: 480, overtime: 0 },
        ]);
    });

    it('names the absence in a word, never more than vacations or leave', () => {
        expect(absenceLabel('vacation')).toBe('Vacaciones');
        expect(absenceLabel('leave')).toBe('Permiso');
        expect(absenceLabel(null)).toBeNull();
        expect(absenceLabel(undefined)).toBeNull();
    });
});

describe('a day folded', () => {
    const mark = (time: string, extra: object = {}) => ({
        id: time,
        type: 'start',
        time,
        nextDay: false,
        corrected: false,
        reason: null,
        evidence: 'strong',
        ...extra,
    });

    it('is named short, «Lun 9»', () => {
        expect(dayShort('2026-11-09')).toBe('Lun 9');
        expect(dayShort('2026-11-15')).toBe('Dom 15');
    });

    it('says the range of its marks, or why it has none', () => {
        expect(
            dayRange({ ...day, marks: [mark('06:00'), mark('14:00')] }),
        ).toBe('06:00 a 14:00');
        expect(
            dayRange({
                ...day,
                marks: [mark('22:00'), mark('06:00', { nextDay: true })],
            }),
        ).toBe('22:00 a 06:00 del día siguiente');
        expect(dayRange({ ...day, marks: [mark('09:00')] })).toBe('09:00');
        expect(dayRange({ ...day, restDay: true })).toBe('Descanso');
        expect(dayRange({ ...day, absence: 'vacation' })).toBe('Vacaciones');
        expect(dayRange(day)).toBe('Sin marcas');
    });

    it('opens by itself only when something in it asks to be looked at: a correction, a lateness or no end', () => {
        expect(dayNeedsLook({ ...day, marks: [mark('09:00')] })).toBe(false);
        expect(
            dayNeedsLook({
                ...day,
                marks: [mark('09:00', { corrected: true })],
            }),
        ).toBe(true);
        expect(dayNeedsLook({ ...day, lateMinutes: 15 })).toBe(true);
        expect(dayNeedsLook({ ...day, lateMinutes: 0 })).toBe(false);
        expect(dayNeedsLook({ ...day, missingEnd: true })).toBe(true);
    });
});

describe('weekActions', () => {
    const urls = { confirmUrl: '/c', disputeUrl: '/d' };

    it('offers only to read the week until the device verifies the person', () => {
        expect(weekActions(null, false)).toEqual(['read']);
    });

    it('offers to confirm and to say what is not right when the week allows each', () => {
        expect(weekActions({ ...week, ...urls }, false)).toEqual([
            'confirm',
            'dispute',
        ]);
        expect(
            weekActions({ ...week, confirmUrl: null, disputeUrl: '/d' }, false),
        ).toEqual(['dispute']);
        expect(
            weekActions({ ...week, confirmUrl: null, disputeUrl: null }, false),
        ).toEqual([]);
    });

    it('offers nothing while the person writes what is not right: the form has its own buttons', () => {
        expect(weekActions({ ...week, ...urls }, true)).toEqual([]);
    });
});
