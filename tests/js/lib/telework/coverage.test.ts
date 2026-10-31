import { describe, expect, it } from 'vitest';
import { coverageMessage, totalMissing } from '@/lib/telework/coverage';

describe('coverageMessage', () => {
    it('is null when nothing is missing', () => {
        expect(coverageMessage([])).toBeNull();
    });

    it('names a single missing requirement', () => {
        expect(coverageMessage(['Canal de violencia familiar'])).toBe(
            'Falta una pregunta para: Canal de violencia familiar.',
        );
    });

    it('lists several', () => {
        expect(coverageMessage(['A', 'B'])).toBe(
            'Faltan preguntas para: A; B.',
        );
    });
});

describe('totalMissing', () => {
    it('adds the gaps of both sections', () => {
        expect(totalMissing({ '1': ['a'], '2': ['b', 'c'] })).toBe(3);
        expect(totalMissing({})).toBe(0);
    });
});
