import { describe, expect, it } from 'vite-plus/test';

import {
    addCenter,
    itemLetters,
    missingLabels,
    removeCenter,
} from '@/lib/telework/documents';
import type { DocumentKindOption } from '@/types';

const policy: DocumentKindOption = {
    value: 'policy',
    label: 'Política de Teletrabajo',
    numeral: '5.2',
    items: [
        { value: 'a', label: 'Cultura de prevención' },
        { value: 'b', label: 'Comunicación' },
    ],
};

describe('document items', () => {
    it('names the missing items with their letter', () => {
        expect(missingLabels(policy, ['b'])).toEqual(['b) Comunicación']);
        expect(missingLabels(policy, [])).toEqual([]);
    });

    it('ignores a missing letter the kind does not know', () => {
        expect(missingLabels(policy, ['z', 'a'])).toEqual([
            'a) Cultura de prevención',
        ]);
    });

    it('gives no label without the kind', () => {
        expect(missingLabels(undefined, ['a'])).toEqual([]);
    });

    it('lists the letters of a kind', () => {
        expect(itemLetters(policy)).toEqual(['a', 'b']);
        expect(itemLetters(undefined)).toEqual([]);
    });
});

describe('chosen centers', () => {
    const north = { value: 'n', label: 'Norte' };
    const south = { value: 's', label: 'Sur' };

    it('adds a center once', () => {
        expect(addCenter([north], south)).toEqual([north, south]);
        expect(addCenter([north], north)).toEqual([north]);
    });

    it('does not add the empty choice', () => {
        expect(addCenter([north], { value: '', label: 'Todos' })).toEqual([
            north,
        ]);
    });

    it('removes a center', () => {
        expect(removeCenter([north, south], 'n')).toEqual([south]);
    });
});
