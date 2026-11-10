import { describe, expect, it } from 'vitest';
import { balanceNote, balancesQuery } from '@/lib/leave/balances';

describe('balancesQuery', () => {
    it('leaves the first tab with no search with a plain address', () => {
        expect(balancesQuery({ q: '  ', tab: 'all' })).toEqual({});
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
        expect(balanceNote({ advanced: 0, nextAnniversary: null })).toBe(
            'Sin fecha de ingreso',
        );
    });

    it('counts the days given in advance, in the right number', () => {
        expect(
            balanceNote({ advanced: 1, nextAnniversary: '2027-01-15' }),
        ).toBe('1 día adelantado');
        expect(
            balanceNote({ advanced: 3, nextAnniversary: '2027-01-15' }),
        ).toBe('3 días adelantados');
    });

    it('says a person with no advanced days is up to date', () => {
        expect(
            balanceNote({ advanced: 0, nextAnniversary: '2027-01-15' }),
        ).toBe('Al corriente');
    });
});
