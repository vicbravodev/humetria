import { describe, expect, it } from 'vitest';
import {
    absentNote,
    flagLabel,
    hoursSplit,
    hoursText,
    overviewQuery,
    overviewRibbon,
    weekBadge,
    weekNotes,
} from '@/lib/attendance/overview';

describe('overviewQuery', () => {
    it('sends only what differs from the default view', () => {
        expect(
            overviewQuery({ q: '  ', status: 'all', week: '', center: '' }),
        ).toEqual({});
        expect(
            overviewQuery({
                q: ' Ana ',
                status: 'at_risk',
                week: '2026-11-02',
                center: 'c-1',
            }),
        ).toEqual({
            q: 'Ana',
            status: 'at_risk',
            week: '2026-11-02',
            center: 'c-1',
        });
    });
});

describe('flagLabel', () => {
    it('names each legal limit in plain Spanish', () => {
        expect(flagLabel('day_over_12_hours')).toBe('Un día de más de 12 h');
        expect(flagLabel('daily_overtime_over_4')).toBe(
            'Más de 4 h extra en un día',
        );
        expect(flagLabel('overtime_days_over_4')).toBe(
            'Horas extra en más de 4 días',
        );
        expect(flagLabel('triple_cap_exceeded')).toBe(
            'Más de 4 h al triple en la semana',
        );
        expect(flagLabel('something_new')).toBe('Rebasó un tope legal');
    });
});

describe('hoursText', () => {
    it('gives the ordinary hours and the overtime only when there is some', () => {
        expect(hoursText(2400, 0)).toBe('40 h');
        expect(hoursText(480, 90)).toBe('8 h · 1 h 30 min extra');
        expect(hoursText(0, 0)).toBe('Sin horas');
    });
});

describe('weekBadge', () => {
    it('says where a person stands with the week', () => {
        expect(weekBadge('confirmed', true)).toEqual({
            label: 'Confirmada',
            variant: 'success',
        });
        expect(weekBadge('disputed', true)).toEqual({
            label: 'Objetada',
            variant: 'destructive',
        });
        expect(weekBadge('pending', true)).toEqual({
            label: 'Sin confirmar',
            variant: 'warning',
        });
    });

    it('keeps a week in course apart: nobody can answer it yet', () => {
        expect(weekBadge('pending', false)).toEqual({
            label: 'En curso',
            variant: 'secondary',
        });
    });
});

describe('weekNotes', () => {
    it('lists the limits broken, or warns of a week near the cap', () => {
        expect(
            weekNotes({ flags: ['day_over_12_hours'], atRisk: true }),
        ).toEqual(['Un día de más de 12 h']);
        expect(weekNotes({ flags: [], atRisk: true })).toEqual([
            'Cerca del tope de horas extra',
        ]);
        expect(weekNotes({ flags: [], atRisk: false })).toEqual([]);
    });
});

describe('hoursSplit', () => {
    it('gives the hours of the week as a total and, apart, the overtime', () => {
        expect(hoursSplit(2400, 300)).toEqual({
            total: '45 h',
            extra: '5 h extra',
        });
        expect(hoursSplit(480, 90)).toEqual({
            total: '9 h 30 min',
            extra: '1 h 30 min extra',
        });
    });

    it('has no extra line without overtime, and says so when nothing was worked', () => {
        expect(hoursSplit(2400, 0)).toEqual({ total: '40 h', extra: null });
        expect(hoursSplit(0, 0)).toEqual({ total: 'Sin horas', extra: null });
    });
});

describe("days away in RH's overview", () => {
    const days = [
        { worked: 0, overtime: 0 },
        { worked: 600, overtime: 120 },
        { worked: 0, overtime: 0 },
    ];

    it('marks on the ribbon the days the person was away', () => {
        expect(overviewRibbon({ days, absentDays: [0, 2] })).toEqual([
            { worked: 0, overtime: 0, absent: true },
            { worked: 600, overtime: 120, absent: false },
            { worked: 0, overtime: 0, absent: true },
        ]);
    });

    it('says how many days, and nothing when none', () => {
        expect(absentNote([])).toBeNull();
        expect(absentNote([1])).toBe('Ausente 1 día');
        expect(absentNote([1, 2, 6])).toBe('Ausente 3 días');
    });
});
