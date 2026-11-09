import { describe, expect, it } from 'vitest';
import {
    confirmationBadge,
    dayHours,
    endText,
    reportQuery,
    summaryHint,
} from '@/lib/attendance/report';
import type { ReportDay } from '@/types';

const day = (overrides: Partial<ReportDay> = {}): ReportDay => ({
    date: '2027-01-26',
    start: '08:00',
    end: '16:00',
    endDate: '2027-01-26',
    breakMinutes: 0,
    workedMinutes: 480,
    ordinaryMinutes: 480,
    doubleMinutes: 0,
    tripleMinutes: 0,
    restDayMinutes: 0,
    evidence: 'Fuerte',
    corrections: [],
    confirmation: 'sin confirmar',
    flags: [],
    ...overrides,
});

describe('reportQuery', () => {
    it('sends the center, the period and only a search that has text', () => {
        expect(
            reportQuery({
                center: 'c-1',
                from: '2027-01-25',
                to: '2027-01-31',
                q: '  ',
            }),
        ).toEqual({ center: 'c-1', from: '2027-01-25', to: '2027-01-31' });
        expect(
            reportQuery({ center: '', from: '', to: '', q: ' Ana ' }),
        ).toEqual({ q: 'Ana' });
    });
});

describe('confirmationBadge', () => {
    it('says where the week of the day stands; silence is not conformity', () => {
        expect(confirmationBadge('confirmado')).toEqual({
            label: 'Confirmada',
            variant: 'success',
        });
        expect(confirmationBadge('objetada')).toEqual({
            label: 'Objetada',
            variant: 'destructive',
        });
        expect(confirmationBadge('sin confirmar')).toEqual({
            label: 'Sin confirmar',
            variant: 'warning',
        });
    });
});

describe('endText', () => {
    it('gives the end, says when it is the next day, and «sin fin» with none', () => {
        expect(endText(day())).toBe('16:00');
        expect(endText(day({ end: '06:00', endDate: '2027-01-27' }))).toBe(
            '06:00 (día siguiente)',
        );
        expect(endText(day({ end: null, endDate: null }))).toBe('sin fin');
    });
});

describe('dayHours', () => {
    it('splits the day as the law pays it, only what it has', () => {
        expect(dayHours(day())).toBe('8 h');
        expect(
            dayHours(
                day({
                    ordinaryMinutes: 480,
                    doubleMinutes: 60,
                    tripleMinutes: 30,
                }),
            ),
        ).toBe('8 h · 1 h doble · 30 min triple');
        expect(dayHours(day({ ordinaryMinutes: 0, restDayMinutes: 480 }))).toBe(
            '8 h en descanso',
        );
        expect(dayHours(day({ ordinaryMinutes: 0, workedMinutes: 0 }))).toBe(
            'Sin horas',
        );
    });
});

describe('summaryHint', () => {
    it('counts the people and their days', () => {
        expect(summaryHint(1, 1)).toBe('1 persona · 1 día');
        expect(summaryHint(3, 12)).toBe('3 personas · 12 días');
    });
});
