import { describe, expect, it } from 'vitest';
import {
    balanceHint,
    balanceNote,
    balancesQuery,
    availablePercent,
    newPeriodSoon,
} from '@/lib/leave/balances';

describe('balancesQuery', () => {
    it('always names the tab, so the one that is chosen is the one that opens (everybody included)', () => {
        expect(balancesQuery({ q: '  ', tab: 'all' })).toEqual({ tab: 'all' });
    });

    it('keeps the tab and the trimmed search', () => {
        expect(balancesQuery({ q: ' ana ', tab: 'missing' })).toEqual({
            tab: 'missing',
            q: 'ana',
        });
    });
});

describe('balanceNote', () => {
    it('says a person with no start date has none', () => {
        expect(
            balanceNote({ advanced: 0, available: 20, nextAnniversary: null }),
        ).toBe('Sin fecha de ingreso');
    });

    it('counts the days given in advance, in the right number', () => {
        expect(
            balanceNote({
                advanced: 1,
                available: -1,
                nextAnniversary: '2027-01-15',
            }),
        ).toBe('1 día adelantado');
        expect(
            balanceNote({
                advanced: 3,
                available: -3,
                nextAnniversary: '2027-01-15',
            }),
        ).toBe('3 días adelantados');
    });

    it('says a person with no advanced days is up to date', () => {
        expect(
            balanceNote({
                advanced: 0,
                available: 12,
                nextAnniversary: '2027-01-15',
            }),
        ).toBe('Al corriente');
    });

    it('does not call «Al corriente» a person with no days left', () => {
        expect(
            balanceNote({
                advanced: 0,
                available: 0,
                nextAnniversary: '2027-01-15',
            }),
        ).toBe('Sin días');
    });
});

describe('availablePercent', () => {
    it('is the days left over the days given, cut to the bar', () => {
        expect(availablePercent(23, 25)).toBe(92);
        expect(availablePercent(0, 25)).toBe(0);
        expect(availablePercent(30, 25)).toBe(100);
        expect(availablePercent(-2, 25)).toBe(0);
    });

    it('is nothing when the whole is not known', () => {
        expect(availablePercent(19, null)).toBeNull();
        expect(availablePercent(19, 0)).toBeNull();
    });
});

describe('newPeriodSoon', () => {
    it('says how close the next period is when it is within a month', () => {
        expect(newPeriodSoon('2026-12-01', '2026-11-11')).toBe(
            'Nuevo periodo en 20 días',
        );
        expect(newPeriodSoon('2026-11-12', '2026-11-11')).toBe(
            'Nuevo periodo mañana',
        );
        expect(newPeriodSoon('2026-11-11', '2026-11-11')).toBe(
            'Nuevo periodo hoy',
        );
        expect(newPeriodSoon('2026-12-11', '2026-11-11')).toBe(
            'Nuevo periodo en 30 días',
        );
    });

    it('says nothing when it is further, past, unknown or the date is wrong', () => {
        expect(newPeriodSoon('2026-12-12', '2026-11-11')).toBeNull();
        expect(newPeriodSoon('2026-11-10', '2026-11-11')).toBeNull();
        expect(newPeriodSoon(null, '2026-11-11')).toBeNull();
        expect(newPeriodSoon('nope', '2026-11-11')).toBeNull();
    });
});

describe('balanceHint', () => {
    it('says the days there are and, only when some wait for an answer, how many', () => {
        expect(balanceHint(12, 0)).toBe('12 días disponibles');
        expect(balanceHint(1, 0)).toBe('1 día disponible');
        expect(balanceHint(6, 2)).toBe('6 días disponibles · 2 en espera');
    });
});
