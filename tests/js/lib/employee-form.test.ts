import { describe, expect, it } from 'vite-plus/test';

import {
    catalogOption,
    clearableOptions,
    namedOption,
    namedSource,
} from '@/lib/employee-form';

const groups = [
    { value: 'unionized', label: 'Sindicalizado' },
    { value: 'trusted', label: 'Confianza' },
];

describe('namedOption', () => {
    it('shows a name as the option that submits it', () => {
        expect(namedOption('Operador')).toEqual({
            value: 'Operador',
            label: 'Operador',
        });
    });

    it('has no option for no name', () => {
        expect(namedOption('')).toBeUndefined();
    });
});

describe('catalogOption', () => {
    it('finds the option of a value', () => {
        expect(catalogOption(groups, 'trusted')).toEqual(groups[1]);
    });

    it('has none for an empty or unknown value', () => {
        expect(catalogOption(groups, '')).toBeUndefined();
        expect(catalogOption(groups, 'directivo')).toBeUndefined();
    });
});

describe('clearableOptions', () => {
    it('puts first the option that clears the choice', () => {
        expect(clearableOptions(groups, 'Sin grupo')).toEqual([
            { value: '', label: 'Sin grupo' },
            ...groups,
        ]);
    });
});

describe('namedSource', () => {
    it('searches on the server, lets the person add a name and offers first to clear it', () => {
        const source = namedSource(
            '/options',
            { value: 'Operador', label: 'Operador' },
            'Sin categoría',
        );

        expect(source.initial).toEqual([
            { value: '', label: 'Sin categoría' },
            { value: 'Operador', label: 'Operador' },
        ]);
        expect(source.allowNew).toBe(true);
        expect(source.minLength).toBe(1);
    });

    it('offers only to clear it when nothing is chosen', () => {
        expect(namedSource('/options', undefined, 'Sin nivel').initial).toEqual(
            [{ value: '', label: 'Sin nivel' }],
        );
    });
});
