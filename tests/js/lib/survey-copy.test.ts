import { describe, expect, it } from 'vite-plus/test';

import { captionOf, introCopy, progressOf, steps } from '@/lib/survey';
import type { SurveyInstrument, SurveySection } from '@/types';

const gated: SurveySection = {
    title: null,
    instruction: 'Si su respuesta fue…',
    gate: {
        key: 'serves_clients',
        question: '¿Clientes?',
        instruction: 'Si su respuesta fue…',
    },
    items: [5, 6].map((number) => ({ number, text: `Pregunta ${number}` })),
};

const plain: SurveySection = {
    title: null,
    instruction: 'Instrucción',
    gate: null,
    items: [1, 2, 3].map((number) => ({ number, text: `Pregunta ${number}` })),
};

const instrument: SurveyInstrument = {
    key: 'nom035.guide-ii',
    title: 'Factores',
    shortTitle: 'Guía II',
    identified: false,
    status: 'pending',
    options: [{ value: 'always', label: 'Siempre' }],
    sections: [plain, gated],
};

describe('introCopy', () => {
    it('keeps the NOM-035 presentation for its campaigns', () => {
        const copy = introCopy('nom035_evaluation');

        expect(copy.title).toBe('Evaluación de factores de riesgo psicosocial');
        expect(copy.purpose).toContain('NOM-035-STPS-2018');
        expect(copy.period).toContain('dos últimos meses');
        expect(copy.noun).toBe('guía');
        expect(introCopy('nom035_trauma')).toEqual(copy);
    });

    it('presents the Ley Silla survey as what it is, without NOM-035 or a two-month window', () => {
        const copy = introCopy('seating_survey');

        expect(copy.title).toBe('Encuesta sobre su trabajo de pie');
        expect(JSON.stringify(copy)).not.toContain('NOM-035');
        expect(copy.period).toBeNull();
        expect(copy.noun).toBe('encuesta');
    });
});

describe('captionOf', () => {
    it('counts the gate in the block, the same total as the progress above', () => {
        const answers = { serves_clients: 'yes' };
        const step = steps(instrument, answers, 1).find((candidate) =>
            candidate.keys.includes('5'),
        );

        expect(captionOf(gated, 5)).toBe('Pregunta 2 de 3');
        expect(
            step === undefined
                ? null
                : progressOf(instrument, answers, step).inSection,
        ).toBe(3);
    });

    it('counts a block with no gate by its own questions', () => {
        expect(captionOf(plain, 2)).toBe('Pregunta 2 de 3');
    });
});
