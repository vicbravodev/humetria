import { describe, expect, it } from 'vite-plus/test';
import {
    gradeName,
    overridesHeadline,
    salaryQuery,
    salaryStanding,
    tabulatorHeadline,
} from '@/lib/compensation/salaries';

describe('salaryQuery', () => {
    it('is empty for a plain list so it has a plain address', () => {
        expect(salaryQuery({ q: '' })).toEqual({});
        expect(salaryQuery({ q: '   ' })).toEqual({});
    });

    it('carries the search trimmed', () => {
        expect(salaryQuery({ q: ' operador ' })).toEqual({ q: 'operador' });
    });
});

describe('tabulatorHeadline', () => {
    it('says there is nothing yet, one grade or many', () => {
        expect(tabulatorHeadline(0)).toBe('Todavía no hay categorías.');
        expect(tabulatorHeadline(1)).toBe('1 categoría y nivel.');
        expect(tabulatorHeadline(12)).toBe('12 categorías y niveles.');
    });
});

describe('gradeName', () => {
    it('writes the category and its level', () => {
        expect(gradeName({ category: 'Operador', level: 'A' })).toBe(
            'Operador · A',
        );
    });
});

describe('salaryStanding', () => {
    it('is in force when it is the one of today, later when it starts after and earlier otherwise', () => {
        const current = '2026-01-01';

        expect(salaryStanding('2026-01-01', current)).toBe('current');
        expect(salaryStanding('2027-01-01', current)).toBe('upcoming');
        expect(salaryStanding('2025-01-01', current)).toBe('past');
    });

    it('is later when nothing is in force yet', () => {
        expect(salaryStanding('2027-01-01', null)).toBe('upcoming');
    });
});

describe('overridesHeadline', () => {
    it('says there are none, one person or many', () => {
        expect(overridesHeadline(0)).toBe(
            'Todavía no hay sueldos individuales.',
        );
        expect(overridesHeadline(1)).toBe('1 persona con sueldo individual.');
        expect(overridesHeadline(12)).toBe(
            '12 personas con sueldo individual.',
        );
    });
});
