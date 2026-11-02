import { describe, expect, it } from 'vite-plus/test';
import { followUpsFor, suggestionsFor } from '@/lib/assistant/suggestions';

describe('suggestionsFor', () => {
    it('offers four general questions on no particular page', () => {
        expect(suggestionsFor(null)).toHaveLength(4);
        expect(suggestionsFor(null)[0]).toBe(
            '¿Qué me falta para estar al día?',
        );
    });

    it('offers questions about the campaign or the center the person has open', () => {
        expect(suggestionsFor({ kind: 'campaign', id: 'a' })).toContain(
            '¿Dónde está el riesgo más alto?',
        );
        expect(suggestionsFor({ kind: 'work_center', id: 'a' })).toContain(
            '¿Qué pendientes tiene este centro?',
        );
    });
});

describe('followUpsFor', () => {
    it('follows the last card the answer carried', () => {
        expect(followUpsFor(['progress', 'risk_levels'])).toEqual([
            '¿Qué dice la norma que debo hacer?',
            '¿Qué pendientes tengo primero?',
        ]);
    });

    it('offers nothing after an answer with no card, or after a list', () => {
        expect(followUpsFor([])).toEqual([]);
        expect(followUpsFor(['list'])).toEqual([]);
        expect(followUpsFor(['otra'])).toEqual([]);
    });
});
