import { describe, expect, it } from 'vitest';
import {
    blankRow,
    draftsOf,
    fixedDaysLine,
    floorFor,
    floorText,
    previewDays,
    saveBarText,
} from '@/lib/leave/policy';

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

describe('fixedDaysLine', () => {
    it('says «1 día fijo» in the singular and «3 días fijos» in the plural', () => {
        expect(fixedDaysLine(1)).toBe('1 día fijo cada año');
        expect(fixedDaysLine(3)).toBe('3 días fijos cada año');
    });
});

describe('previewDays', () => {
    const row = (
        kind: 'tier' | 'fixed',
        [from, to]: [string, string],
        days: string,
    ) => ({ key: 0, kind, label: '', yearFrom: from, yearTo: to, days });

    it('is the law when the policy has no rows', () => {
        expect(previewDays([], FLOORS)).toEqual([12, 14, 16, 18, 20, 22]);
    });

    it('takes the tier of each year and the law where no tier reaches', () => {
        expect(previewDays([row('tier', ['1', '3'], '20')], FLOORS)).toEqual([
            20, 20, 20, 18, 20, 22,
        ]);
    });

    it('never shows a year under the law, even with a row typed under it', () => {
        expect(previewDays([row('tier', ['1', '2'], '10')], FLOORS)[0]).toBe(
            12,
        );
    });

    it('adds the fixed days to every year, and ignores rows half typed', () => {
        expect(
            previewDays(
                [
                    row('fixed', ['', ''], '2'),
                    row('tier', ['', '3'], '20'),
                    row('tier', ['1', '3'], ''),
                    row('fixed', ['', ''], ''),
                ],
                FLOORS,
            ),
        ).toEqual([14, 16, 18, 20, 22, 24]);
    });
});

describe('saveBarText', () => {
    it('says there is nothing to save, that something is, or what to fix first', () => {
        expect(saveBarText({ dirty: false, errors: 0 })).toBe(
            'Sin cambios por guardar',
        );
        expect(saveBarText({ dirty: true, errors: 0 })).toBe(
            'Hay cambios sin guardar',
        );
        expect(saveBarText({ dirty: true, errors: 1 })).toBe(
            'Corrige 1 campo para guardar',
        );
        expect(saveBarText({ dirty: false, errors: 3 })).toBe(
            'Corrige 3 campos para guardar',
        );
    });
});
