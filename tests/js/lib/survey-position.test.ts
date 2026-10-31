import { describe, expect, it } from 'vite-plus/test';

import { steps } from '@/lib/survey';
import { resumeKey, stepHolding } from '@/lib/survey-position';
import type { SurveyInstrument, SurveySection } from '@/types';

const section = (
    numbers: number[],
    gate: SurveySection['gate'] = null,
): SurveySection => ({
    title: null,
    instruction: 'Instrucción',
    gate,
    items: numbers.map((number) => ({ number, text: `Pregunta ${number}` })),
});

/** A small Guía II: four plain items in two sections, then the clients and supervision blocks. */
const guideTwo: SurveyInstrument = {
    key: 'nom035.guide-ii',
    title: 'Factores de riesgo psicosocial',
    shortTitle: 'Guía II',
    identified: false,
    status: 'pending',
    options: [{ value: 'never', label: 'Nunca' }],
    sections: [
        section([1, 2, 3]),
        section([4]),
        section([5, 6], {
            key: 'serves_clients',
            question: '¿Clientes?',
            instruction: 'Si su respuesta fue…',
        }),
        section([7], {
            key: 'supervises',
            question: '¿Jefe?',
            instruction: 'Si su respuesta fue…',
        }),
    ],
};

describe('resumeKey', () => {
    it('starts at the first question with nothing answered', () => {
        expect(resumeKey(steps(guideTwo, {}, 3), {})).toBe('1');
    });

    it('resumes at the first screen with a question still unanswered', () => {
        const answers = { '1': 'never', '2': 'never', '3': 'never' };

        expect(resumeKey(steps(guideTwo, answers, 3), answers)).toBe('4');
        expect(resumeKey(steps(guideTwo, { '1': 'never' }, 3), {})).toBe('1');
    });

    it('resumes at the very question left unanswered, so a phone shows that one', () => {
        const answers = { '1': 'never', '3': 'never' };

        expect(resumeKey(steps(guideTwo, answers, 3), answers)).toBe('2');
    });

    it('stays on the last screen when everything is answered', () => {
        const answers = {
            '1': 'never',
            '2': 'never',
            '3': 'never',
            '4': 'never',
            serves_clients: 'no',
            supervises: 'no',
        };

        expect(resumeKey(steps(guideTwo, answers, 3), answers)).toBe(
            'supervises',
        );
    });

    it('has nothing to resume in a guide without screens', () => {
        expect(resumeKey([], {})).toBeUndefined();
    });
});

describe('stepHolding', () => {
    it('finds the screen that holds a question, however many fit on a screen', () => {
        expect(stepHolding(steps(guideTwo, {}, 3), '3')).toBe(0);
        expect(stepHolding(steps(guideTwo, {}, 1), '3')).toBe(2);
        expect(stepHolding(steps(guideTwo, {}, 3), 'serves_clients')).toBe(2);
    });

    it('falls back to the last screen when the question is no longer asked', () => {
        expect(stepHolding(steps(guideTwo, {}, 3), '5')).toBe(3);
        expect(stepHolding(steps(guideTwo, {}, 3), undefined)).toBe(3);
    });
});
