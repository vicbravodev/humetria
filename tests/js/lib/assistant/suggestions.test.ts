import { describe, expect, it } from 'vite-plus/test';
import {
    followUpsFor,
    liveFactsByQuestion,
    startersFor,
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

describe('liveFactsByQuestion', () => {
    it('puts each fact of the server under the first question it answers, once', () => {
        expect(
            liveFactsByQuestion({
                pending: '3 pendientes; el primero vence el 14 nov 2026',
                participation: '142 de 186 cuestionarios contestados (76 %)',
                risk: 'Se puede ver cuando cierre la campaña',
                telework: 'Teletrabajo: 2 de 3 centros con su lista completa',
                'invite.campaigns':
                    'Cierra el 14 nov 2026. ¿Te cuento cómo va?',
            }),
        ).toEqual({
            '¿Qué me falta para estar al día?':
                '3 pendientes; el primero vence el 14 nov 2026',
            '¿Cómo va la participación?':
                '142 de 186 cuestionarios contestados (76 %)',
            '¿Dónde está el riesgo más alto?':
                'Se puede ver cuando cierre la campaña',
            'Explícame la NOM-037':
                'Teletrabajo: 2 de 3 centros con su lista completa',
        });
    });

    it('gives nothing for a fact that did not come', () => {
        expect(liveFactsByQuestion({})).toEqual({});
    });
});

describe('startersFor', () => {
    const facts = { participation: '3 de 8 cuestionarios contestados (38 %)' };

    it('puts the facts of the company under the general questions', () => {
        expect(startersFor(null, facts)[1]).toEqual({
            text: '¿Cómo va la participación?',
            live: '3 de 8 cuestionarios contestados (38 %)',
        });
    });

    it('shows the questions of one campaign alone: a fact of the whole company would not be exact there', () => {
        expect(
            startersFor({ kind: 'campaign', id: 'a' }, facts).map(
                (starter) => starter.live,
            ),
        ).toEqual([null, null, null]);
    });
});
