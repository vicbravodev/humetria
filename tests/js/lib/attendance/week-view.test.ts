import { describe, expect, it } from 'vitest';
import {
    absenceLabel,
    dayEvidence,
    ribbonDays,
    weekActions,
    weekHeading,
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
    previousUrl: '/a/acme/week/2026-10-26',
    nextUrl: null,
};

describe('weekHeading', () => {
    it.each([
        ['pending', null, 'Su semana está lista'],
        ['pending', 'no_marks', 'Su semana por revisar'],
        ['confirmed', null, 'Semana confirmada'],
        ['disputed', null, 'Semana objetada'],
        ['open', null, 'Su semana en curso'],
    ])('titles a %s week (blocked: %s) as «%s»', (status, blocked, title) => {
        expect(weekHeading({ ...week, status, blocked }).title).toBe(title);
    });

    it('names the person and the week under the title', () => {
        expect(weekHeading(week).text).toBe(
            'Ana López · Del 2 al 8 de noviembre de 2026',
        );
    });
});

describe('weekTotal', () => {
    it('adds the hours worked and says how the law pays them', () => {
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
            parts: '16 h ordinarias · 1 h dobles',
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
                    doubleMinutes: 60,
                    tripleMinutes: 30,
                    restDayMinutes: 30,
                },
            }).parts,
        ).toBe(
            '8 h ordinarias · 1 h dobles · 30 min triples · 30 min en descanso',
        );
    });

    it('says there are no hours for a week without marks', () => {
        expect(
            weekTotal({ ...week, days: [{ ...day, workedMinutes: 0 }] }),
        ).toEqual({
            total: '0 h',
            parts: 'Sin horas trabajadas',
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

describe('dayEvidence', () => {
    const marks = (...levels: (string | null)[]) =>
        levels.map((evidence, index) => ({
            id: `m${index}`,
            type: 'start',
            time: '09:00',
            nextDay: false,
            corrected: false,
            reason: null,
            evidence,
        }));

    it('is as strong as the weakest mark of the day', () => {
        expect(dayEvidence(marks('strong', 'strong'))).toEqual({
            dots: 3,
            text: 'Fuerte',
        });
        expect(dayEvidence(marks('strong', 'medium'))).toEqual({
            dots: 2,
            text: 'Media',
        });
        expect(dayEvidence(marks('strong', 'medium', 'basic'))).toEqual({
            dots: 1,
            text: 'Básica',
        });
        expect(dayEvidence(marks('strong', 'corrected'))).toEqual({
            dots: 1,
            text: 'Corregida',
        });
    });

    it('says nothing of a day with no marks or with a mark whose level it does not know', () => {
        expect(dayEvidence([])).toBeNull();
        expect(dayEvidence(marks('strong', null))).toBeNull();
        expect(dayEvidence(marks('x'))).toBeNull();
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
