import { describe, expect, it } from 'vitest';
import { blankRow, draftsOf, floorFor, floorText } from '@/lib/leave/policy';

const FLOORS = [12, 14, 16, 18, 20, 22];

describe('floorFor', () => {
    it.each([
        ['1', 12],
        ['5', 20],
        ['6', 22],
        // A longer service has the floor of the last year the table holds.
        ['99', 22],
    ])('the floor of year %s is %d days', (year, days) => {
        expect(floorFor(FLOORS, year)).toBe(days);
    });

    it.each(['', '0', '-3', 'x', '2.5'])('has none for "%s"', (year) => {
        expect(floorFor(FLOORS, year)).toBeNull();
    });
});

describe('floorText', () => {
    it('names the minimum of the last year of the range', () => {
        expect(floorText(FLOORS, '5')).toBe('Mínimo legal: 20 días');
    });

    it('says nothing before the range is known', () => {
        expect(floorText(FLOORS, '')).toBe('');
    });
});

describe('blankRow', () => {
    it('starts a tier on the first year, or right after the last one given', () => {
        expect(blankRow('tier', 1, [])).toMatchObject({
            key: 1,
            kind: 'tier',
            yearFrom: '1',
            yearTo: '',
            days: '',
        });
        expect(
            blankRow('tier', 2, [
                { ...blankRow('tier', 1, []), yearTo: '5' },
                { ...blankRow('fixed', 9, []), yearTo: '' },
            ]).yearFrom,
        ).toBe('6');
    });

    it('starts days that come every year without years', () => {
        expect(blankRow('fixed', 3, [])).toMatchObject({
            kind: 'fixed',
            label: '',
            yearFrom: '',
            yearTo: '',
        });
    });
});

describe('draftsOf', () => {
    it('turns the rows of a policy into what the fields hold', () => {
        expect(
            draftsOf([
                { kind: 'tier', label: null, yearFrom: 1, yearTo: 5, days: 20 },
                {
                    kind: 'fixed',
                    label: 'Cumpleaños',
                    yearFrom: null,
                    yearTo: null,
                    days: 1,
                },
            ]),
        ).toEqual([
            {
                key: 0,
                kind: 'tier',
                label: '',
                yearFrom: '1',
                yearTo: '5',
                days: '20',
            },
            {
                key: 1,
                kind: 'fixed',
                label: 'Cumpleaños',
                yearFrom: '',
                yearTo: '',
                days: '1',
            },
        ]);
    });
});
