import { describe, expect, it } from 'vite-plus/test';
import {
    addGrade,
    addTenureRow,
    benefitQuery,
    benefitsHeadline,
    removeGrade,
    removeTenureRow,
    setTenureRow,
    tenureRowsOf,
    yearStart,
} from '@/lib/compensation/benefits';

describe('benefitQuery', () => {
    it('is empty for a plain list so it has a plain address', () => {
        expect(benefitQuery({ q: '', status: 'active' })).toEqual({});
        expect(benefitQuery({ q: '   ', status: 'active' })).toEqual({});
    });

    it('carries the search trimmed and the archived tab', () => {
        expect(benefitQuery({ q: ' bono ', status: 'archived' })).toEqual({
            q: 'bono',
            status: 'archived',
        });
    });
});

describe('benefitsHeadline', () => {
    it('counts the active benefits with their noun in the right number', () => {
        expect(benefitsHeadline({ active: 0, archived: 0 })).toBe(
            'Todavía no hay prestaciones.',
        );
        expect(benefitsHeadline({ active: 1, archived: 0 })).toBe(
            '1 prestación activa.',
        );
        expect(benefitsHeadline({ active: 11, archived: 0 })).toBe(
            '11 prestaciones activas.',
        );
    });

    it('adds the archived ones when there are some', () => {
        expect(benefitsHeadline({ active: 12, archived: 1 })).toBe(
            '12 prestaciones activas y 1 archivada.',
        );
        expect(benefitsHeadline({ active: 3, archived: 4 })).toBe(
            '3 prestaciones activas y 4 archivadas.',
        );
    });
});

describe('yearStart', () => {
    it('is the first day of the year of the date', () => {
        expect(yearStart(new Date(2026, 10, 3))).toBe('2026-01-01');
        expect(yearStart(new Date(2027, 0, 1))).toBe('2027-01-01');
    });
});

describe('tenure rows', () => {
    it('reads the rows of a term as text, none for a term without a table', () => {
        expect(tenureRowsOf(null)).toEqual([]);
        expect(
            tenureRowsOf([
                { from_years: 1, value: '12', value_text: '12 días' },
                { from_years: 5, value: '12.5', value_text: '12.5 días' },
            ]),
        ).toEqual([
            { from_years: '1', value: '12' },
            { from_years: '5', value: '12.5' },
        ]);
    });

    it('adds a row suggesting the year after the last one', () => {
        expect(addTenureRow([])).toEqual([{ from_years: '1', value: '' }]);
        expect(
            addTenureRow([
                { from_years: '1', value: '12' },
                { from_years: '5', value: '20' },
            ]),
        ).toEqual([
            { from_years: '1', value: '12' },
            { from_years: '5', value: '20' },
            { from_years: '6', value: '' },
        ]);
        expect(addTenureRow([{ from_years: 'x', value: '' }])).toEqual([
            { from_years: 'x', value: '' },
            { from_years: '', value: '' },
        ]);
    });

    it('removes only the row asked for', () => {
        const rows = [
            { from_years: '1', value: '1' },
            { from_years: '2', value: '2' },
            { from_years: '3', value: '3' },
        ];

        expect(removeTenureRow(rows, 1)).toEqual([rows[0], rows[2]]);
        expect(removeTenureRow(rows, 7)).toEqual(rows);
    });

    it('changes one field of one row and leaves the rest', () => {
        const rows = [
            { from_years: '1', value: '1' },
            { from_years: '2', value: '2' },
        ];

        expect(setTenureRow(rows, 1, { value: '9' })).toEqual([
            rows[0],
            { from_years: '2', value: '9' },
        ]);
        expect(setTenureRow(rows, 0, { from_years: '4' })[0]).toEqual({
            from_years: '4',
            value: '1',
        });
    });
});

describe('grades', () => {
    const operator = { value: 'a', label: 'Operador · B' };
    const welder = { value: 'b', label: 'Soldador · A' };

    it('adds a grade once', () => {
        expect(addGrade([operator], welder)).toEqual([operator, welder]);
        expect(addGrade([operator], operator)).toEqual([operator]);
        expect(addGrade([operator], { value: '', label: 'Todas' })).toEqual([
            operator,
        ]);
    });

    it('removes a grade by its value', () => {
        expect(removeGrade([operator, welder], 'a')).toEqual([welder]);
        expect(removeGrade([operator], 'z')).toEqual([operator]);
    });
});
