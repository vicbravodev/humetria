import { describe, expect, it } from 'vite-plus/test';
import {
    followUpsFor,
    startersOf,
    suggestionsFor,
} from '@/lib/assistant/suggestions';

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

describe('startersOf', () => {
    it('gives each first question the live fact that came for it, and none to the rest', () => {
        expect(
            startersOf(['¿Cómo va la participación?', 'Explícame la NOM-037'], {
                '¿Cómo va la participación?': '142 de 186 respondieron (76 %)',
            }),
        ).toEqual([
            {
                text: '¿Cómo va la participación?',
                live: '142 de 186 respondieron (76 %)',
            },
            { text: 'Explícame la NOM-037', live: null },
        ]);
    });

    it('leaves out a live fact with nothing to say', () => {
        expect(
            startersOf(['¿Qué pendientes tengo primero?'], {
                '¿Qué pendientes tengo primero?': '  ',
            }),
        ).toEqual([{ text: '¿Qué pendientes tengo primero?', live: null }]);
    });
});
