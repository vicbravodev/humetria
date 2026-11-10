import { describe, expect, it } from 'vitest';
import { chosenFile, importStep, importSummaryText } from '@/lib/leave/import';

describe('importStep', () => {
    it.each([
        [undefined, 1],
        [{ ready: 0, rejected: 3 }, 2],
        [{ ready: 2, rejected: 0 }, 3],
        [{ ready: 2, rejected: 5 }, 3],
    ] as const)('%j is step %d', (summary, step) => {
        expect(importStep(summary)).toBe(step);
    });
});

describe('importSummaryText', () => {
    it('says only what loads when nothing is left out', () => {
        expect(importSummaryText({ ready: 1, rejected: 0 })).toBe(
            'Se cargarán 1 periodo.',
        );
    });

    it('says how many rows stay out', () => {
        expect(importSummaryText({ ready: 3, rejected: 1 })).toBe(
            'Se cargarán 3 periodos; 1 fila se queda fuera.',
        );
        expect(importSummaryText({ ready: 3, rejected: 2 })).toBe(
            'Se cargarán 3 periodos; 2 filas se quedan fuera.',
        );
    });
});

describe('chosenFile', () => {
    it('is the file when one with content was chosen', () => {
        const file = new File(['a'], 'saldos.csv');

        expect(chosenFile(file)).toBe(file);
    });

    it.each([null, undefined, new File([], 'vacio.csv')])(
        'is none for %j',
        (file) => {
            expect(chosenFile(file)).toBeNull();
        },
    );
});
