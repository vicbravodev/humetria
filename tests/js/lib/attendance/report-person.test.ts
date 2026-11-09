import { describe, expect, it } from 'vitest';
import {
    answerOf,
    matchPreset,
    personHours,
    personNotices,
    personRibbon,
} from '@/lib/attendance/report-person';
import type { ReportDay, ReportPreset } from '@/types';

const day = (overrides: Partial<ReportDay> = {}): ReportDay => ({
    date: '2026-11-02',
    start: '08:00',
    end: '17:00',
    endDate: '2026-11-02',
    breakMinutes: 0,
    workedMinutes: 540,
    ordinaryMinutes: 480,
    doubleMinutes: 60,
    tripleMinutes: 0,
    restDayMinutes: 0,
    evidence: 'Fuerte',
    corrections: [],
    confirmation: 'confirmado',
    flags: [],
    ...overrides,
});

describe('personRibbon', () => {
    it('puts each day on its weekday, Monday first, with its overtime', () => {
        const ribbon = personRibbon([
            day({ date: '2026-11-02' }),
            day({
                date: '2026-11-08',
                workedMinutes: 780,
                doubleMinutes: 120,
                tripleMinutes: 60,
            }),
        ]);

        expect(ribbon).toEqual([
            { worked: 540, overtime: 60 },
            { worked: 0, overtime: 0 },
            { worked: 0, overtime: 0 },
            { worked: 0, overtime: 0 },
            { worked: 0, overtime: 0 },
            { worked: 0, overtime: 0 },
            { worked: 780, overtime: 180 },
        ]);
    });

    it('is a week only when every day falls in the same Monday to Sunday', () => {
        expect(
            personRibbon([
                day({ date: '2026-11-08' }),
                day({ date: '2026-11-09' }),
            ]),
        ).toBeNull();
        expect(
            personRibbon([
                day({ date: '2026-11-02' }),
                day({ date: '2026-11-08' }),
            ]),
        ).not.toBeNull();
    });

    it('has no ribbon for a person without days', () => {
        expect(personRibbon([])).toBeNull();
    });
});

describe('personHours', () => {
    it('adds the hours worked and apart the overtime', () => {
        expect(personHours([day(), day({ date: '2026-11-03' })])).toEqual({
            total: '18 h',
            extra: '2 h extra',
        });
    });

    it('says «Sin horas» when nothing was worked (a day with no end)', () => {
        expect(
            personHours([
                day({
                    workedMinutes: 0,
                    ordinaryMinutes: 0,
                    doubleMinutes: 0,
                    end: null,
                }),
            ]),
        ).toEqual({ total: 'Sin horas', extra: null });
    });
});

describe('personNotices', () => {
    it('lists each distinct flag once, in the order they appear', () => {
        const notices = personNotices([
            day({ flags: ['Sin marca de fin', 'Más de 12 h'] }),
            day({ flags: ['Sin marca de fin'] }),
            day({ flags: [] }),
        ]);

        expect(notices).toEqual(['Sin marca de fin', 'Más de 12 h']);
    });

    it('has none for a person without flags', () => {
        expect(personNotices([day()])).toEqual([]);
    });
});

describe('answerOf', () => {
    it("is the worst answer of the person's weeks: objected, then unanswered, then confirmed", () => {
        const confirmed = day({ confirmation: 'confirmado' });
        const silent = day({ confirmation: 'sin confirmar' });
        const objected = day({ confirmation: 'objetada' });

        expect(answerOf([confirmed, confirmed])).toBe('confirmado');
        expect(answerOf([confirmed, silent])).toBe('sin confirmar');
        expect(answerOf([silent, objected, confirmed])).toBe('objetada');
        expect(answerOf([objected, silent])).toBe('objetada');
    });

    it('has the silence of no days: not confirmed', () => {
        expect(answerOf([])).toBe('sin confirmar');
    });
});

describe('matchPreset', () => {
    const presets: ReportPreset[] = [
        {
            key: 'this-week',
            label: 'Esta semana',
            from: '2026-11-09',
            to: '2026-11-09',
        },
        {
            key: 'last-week',
            label: 'Semana pasada',
            from: '2026-11-02',
            to: '2026-11-08',
        },
    ];

    it('finds the preset whose two dates are the period', () => {
        expect(matchPreset(presets, '2026-11-02', '2026-11-08')).toBe(
            'last-week',
        );
        expect(matchPreset(presets, '2026-11-09', '2026-11-09')).toBe(
            'this-week',
        );
    });

    it('is «other» when only one date matches', () => {
        expect(matchPreset(presets, '2026-11-02', '2026-11-09')).toBe('other');
        expect(matchPreset(presets, '2026-11-03', '2026-11-08')).toBe('other');
    });
});
