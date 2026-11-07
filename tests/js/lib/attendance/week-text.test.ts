import { describe, expect, it } from 'vitest';
import {
    dayTitle,
    duration,
    statusText,
    weekNotices,
    weekTitle,
} from '@/lib/attendance/week-text';
import type { WeekDay, WeekView } from '@/lib/attendance/week';

const day: WeekDay = {
    date: '2026-11-02',
    marks: [],
    workedMinutes: 480,
    scheduledMinutes: 480,
    restDay: false,
    missingEnd: false,
};

const week: WeekView = {
    name: 'Ana López',
    week: { start: '2026-11-02', end: '2026-11-08' },
    status: 'pending',
    answeredAt: null,
    disputeReason: null,
    days: [day],
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
