import { describe, expect, it } from 'vitest';
import {
    dayEvidence,
    dayTitle,
    duration,
    ribbonDays,
    statusText,
    weekHeading,
    weekTotal,
    weekFeedback,
    weekNotices,
    weekTitle,
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

describe('duration', () => {
    it.each([
        [0, '0 h'],
        [45, '45 min'],
        [480, '8 h'],
        [510, '8 h 30 min'],
        [1, '1 min'],
    ])('words %i minutes as %s', (minutes, text) => {
        expect(duration(minutes)).toBe(text);
    });
});

describe('dates', () => {
    it('names a day', () => {
        expect(dayTitle('2026-11-02')).toBe('Lunes 2 de noviembre');
        expect(dayTitle('2026-11-08')).toBe('Domingo 8 de noviembre');
    });

    it('names a week in one month, across two and across two years', () => {
        expect(weekTitle('2026-11-02', '2026-11-08')).toBe(
            'Del 2 al 8 de noviembre de 2026',
        );
        expect(weekTitle('2026-10-26', '2026-11-01')).toBe(
            'Del 26 de octubre al 1 de noviembre de 2026',
        );
        expect(weekTitle('2026-12-28', '2027-01-03')).toBe(
            'Del 28 de diciembre de 2026 al 3 de enero de 2027',
        );
    });
});

describe('statusText', () => {
    it.each([
        {
            status: 'pending',
            answeredAt: null,
            label: 'Sin confirmar',
            detail: 'Revise su semana',
        },
        {
            status: 'confirmed',
            answeredAt: '2026-11-11 12:05',
            label: 'Confirmada',
            detail: 'La confirmó el 11 de noviembre a las 12:05.',
        },
        {
            status: 'disputed',
            answeredAt: '2026-11-10 09:30',
            label: 'Objetada',
            detail: 'La objetó el 10 de noviembre a las 09:30.',
        },
        {
            status: 'open',
            answeredAt: null,
            label: 'En curso',
            detail: 'cuando termine el domingo',
        },
    ])('words $status', ({ status, answeredAt, label, detail }) => {
        const text = statusText({ ...week, status, answeredAt });

        expect(text.label).toBe(label);
        expect(text.detail).toContain(detail);
    });
});

describe('statusText of a week that cannot be confirmed yet', () => {
    it.each([
        ['day_open', 'cuando marque su salida'],
        ['no_marks', 'no tiene marcas suyas'],
    ])('says why for %s', (blocked, detail) => {
        expect(statusText({ ...week, blocked }).detail).toContain(detail);
    });
});

describe('weekNotices', () => {
    it('says nothing of a week within the limits', () => {
        expect(weekNotices(week)).toEqual([]);
    });

    it('names each day with no end, the triple overtime and each limit broken, in plain Spanish', () => {
        expect(
            weekNotices({
                ...week,
                days: [{ ...day, date: '2026-11-03', missingEnd: true }],
                summary: {
                    ...week.summary,
                    tripleMinutes: 90,
                    flags: [
                        'day_over_12_hours',
                        'daily_overtime_over_4',
                        'overtime_days_over_4',
                        'triple_cap_exceeded',
                        'unknown',
                    ],
                },
            }),
        ).toEqual([
            'El martes 3 de noviembre quedó sin salida marcada.',
            'Rebasó el tope de horas extra de la semana: 1 h 30 min se pagan al triple.',
            'Un día pasó de 12 horas de trabajo.',
            'Un día tuvo más de 4 horas extra.',
            'Tuvo horas extra en más de 4 días de la semana.',
            'Las horas extra al triple pasaron de 4 en la semana.',
        ]);
    });
});

describe('weekFeedback', () => {
    it('says why the last action failed, or what it did', () => {
        expect(weekFeedback('No se pudo.', 'Llegó.')).toEqual({
            kind: 'error',
            text: 'No se pudo.',
        });
        expect(weekFeedback(null, 'Llegó.')).toEqual({
            kind: 'done',
            text: 'Llegó.',
        });
        expect(weekFeedback(null, null)).toBeNull();
    });
});

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
