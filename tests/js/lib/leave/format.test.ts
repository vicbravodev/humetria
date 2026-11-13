import { describe, expect, it } from 'vitest';
import {
    balanceLine,
    dateSpan,
    shortDate,
    shortSpan,
    statusTone,
    workingDaysOf,
} from '@/lib/leave/format';
import type { LeaveRequestStatus } from '@/types';

describe('dateSpan', () => {
    it.each([
        ['2026-11-09', '2026-11-09', '9 de noviembre de 2026'],
        ['2026-11-09', '2026-11-20', '9 al 20 de noviembre de 2026'],
        [
            '2026-11-30',
            '2026-12-04',
            '30 de noviembre al 4 de diciembre de 2026',
        ],
        [
            '2026-12-28',
            '2027-01-08',
            '28 de diciembre de 2026 al 8 de enero de 2027',
        ],
    ])('writes %s to %s as «%s»', (startsOn, endsOn, text) => {
        expect(dateSpan(startsOn, endsOn)).toBe(text);
    });

    it('writes nothing for what is not a date', () => {
        expect(dateSpan('', '2026-11-20')).toBe('');
        expect(dateSpan('2026-11-09', 'mañana')).toBe('');
    });
});

describe('shortSpan', () => {
    it.each([
        ['2026-11-09', '2026-11-09', '9 nov 2026'],
        ['2026-11-23', '2026-11-27', '23 al 27 nov 2026'],
        ['2026-11-30', '2026-12-04', '30 nov al 4 dic 2026'],
        ['2026-12-28', '2027-01-08', '28 dic 2026 al 8 ene 2027'],
    ])('writes %s to %s as «%s»', (startsOn, endsOn, text) => {
        expect(shortSpan(startsOn, endsOn)).toBe(text);
    });

    it('writes nothing for what is not a date', () => {
        expect(shortSpan('', '2026-11-20')).toBe('');
    });
});

describe('workingDaysOf', () => {
    it('counts with the noun in its number', () => {
        expect(workingDaysOf(1)).toBe('1 día laborable');
        expect(workingDaysOf(9)).toBe('9 días laborables');
    });
});

describe('statusTone', () => {
    it.each<[LeaveRequestStatus, string]>([
        ['requested', 'default'],
        ['approved', 'success'],
        ['rejected', 'destructive'],
        ['cancelled', 'secondary'],
        ['taken', 'secondary'],
    ])('paints %s as %s', (status, tone) => {
        expect(statusTone(status)).toBe(tone);
    });
});

describe('balanceLine', () => {
    const vacations = {
        type: null,
        periods: [],
        shortBy: 0,
        allowsAdvance: false,
    };

    it('says a leave uses no vacation days', () => {
        expect(balanceLine({ ...vacations, type: 'Paternidad' })).toBe(
            'Este permiso no usa días de vacaciones.',
        );
    });

    it('names the periods the days would come from, the oldest first', () => {
        expect(
            balanceLine({
                ...vacations,
                periods: [
                    { year: 2025, days: 3 },
                    { year: 2026, days: 6 },
                ],
            }),
        ).toBe('Tiene días disponibles: del periodo 2025 (3) y 2026 (6).');
        expect(
            balanceLine({ ...vacations, periods: [{ year: 2026, days: 1 }] }),
        ).toBe('Tiene días disponibles: del periodo 2026 (1).');
    });

    it('says how many days are missing, and whether the policy gives them in advance', () => {
        expect(balanceLine({ ...vacations, shortBy: 4 })).toBe(
            'No tiene días disponibles (le faltan 4). Su política no permite adelantar días, así que no se puede aprobar.',
        );
        expect(
            balanceLine({ ...vacations, shortBy: 1, allowsAdvance: true }),
        ).toBe(
            'No tiene días disponibles (le falta 1). Su política permite adelantarlos: si aprueba, se le adelantan.',
        );
        expect(
            balanceLine({
                ...vacations,
                periods: [{ year: 2026, days: 5 }],
                shortBy: 4,
            }),
        ).toBe(
            'Solo tiene días del periodo 2026 (5): le faltan 4. Su política no permite adelantar días, así que no se puede aprobar.',
        );
    });
});

describe('shortDate', () => {
    it('writes a day short, with its year: «1 mar 2027»', () => {
        expect(shortDate('2027-03-01')).toBe('1 mar 2027');
        expect(shortDate('2026-12-01')).toBe('1 dic 2026');
    });

    it('is empty when the text is not a date', () => {
        expect(shortDate('nope')).toBe('');
    });
});
