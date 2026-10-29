import { describe, expect, it } from 'vite-plus/test';

import type { Option } from '@/lib/option-search';
import { filterOptions, indexOptions, searchNote } from '@/lib/option-search';

const option = (label: string): Option => ({ value: label, label });
const labels = (options: Option[]) => options.map(({ label }) => label);

const STATES = indexOptions(
    [
        'Baja California',
        'Ciudad de México',
        'Estado de México',
        'Michoacán',
        'Nuevo León',
        'Querétaro',
    ].map(option),
);

describe('filterOptions', () => {
    it('gives the first ones, up to the limit, when nothing is typed', () => {
        expect(filterOptions(STATES, '  ', 2)).toEqual({
            options: [option('Baja California'), option('Ciudad de México')],
            more: true,
        });
        expect(filterOptions(STATES, '', 6).more).toBe(false);
    });

    it('ignores case and accents, in the label and in what is typed', () => {
        expect(labels(filterOptions(STATES, 'QUERETARO', 10).options)).toEqual([
            'Querétaro',
        ]);
        expect(labels(filterOptions(STATES, 'leÓn', 10).options)).toEqual([
            'Nuevo León',
        ]);
    });

    it('puts the labels that start with it first, then a word that starts with it, then the rest', () => {
        const index = indexOptions(
            ['Sonora', 'Tamaulipas', 'Baja California Sur', 'Sinaloa'].map(
                option,
            ),
        );

        expect(labels(filterOptions(index, 's', 10).options)).toEqual([
            'Sonora',
            'Sinaloa',
            'Baja California Sur',
            'Tamaulipas',
        ]);
    });

    it('keeps the given order inside each group', () => {
        expect(labels(filterOptions(STATES, 'mexico', 10).options)).toEqual([
            'Ciudad de México',
            'Estado de México',
        ]);
    });

    it('says when there are more matches than it shows', () => {
        expect(filterOptions(STATES, 'o', 1)).toEqual({
            options: [option('Baja California')],
            more: true,
        });
    });

    it('finds nothing for a text no label contains', () => {
        expect(filterOptions(STATES, 'xyz', 10)).toEqual({
            options: [],
            more: false,
        });
    });
});

describe('searchNote', () => {
    const note = (state: Partial<Parameters<typeof searchNote>[0]>) =>
        searchNote({
            status: 'done',
            found: 3,
            more: false,
            minLength: 2,
            ...state,
        });

    it('says nothing when everything found is on screen', () => {
        expect(note({})).toBeNull();
    });

    it('asks to keep typing when some matches were left out', () => {
        expect(note({ more: true })).toBe('Escribe para encontrar más.');
    });

    it('says when nothing matches', () => {
        expect(note({ found: 0 })).toBe('Sin resultados');
    });

    it('says how many letters a server search needs', () => {
        expect(note({ status: 'idle', found: 0 })).toBe(
            'Escribe al menos 2 letras para buscar.',
        );
    });

    it('says it is searching, and when the search failed', () => {
        expect(note({ status: 'loading' })).toBe('Buscando…');
        expect(note({ status: 'error', found: 0 })).toBe(
            'No se pudo buscar. Intenta de nuevo.',
        );
    });
});
