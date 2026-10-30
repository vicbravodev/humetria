import { describe, expect, it } from 'vite-plus/test';

import {
    forgetLink,
    isComplete,
    payload,
    progressOf,
    readAnswers,
    steps,
    storageKey,
    tokenFromPath,
    visibleKeys,
} from '@/lib/survey';
import type { SurveyInstrument, SurveySection } from '@/types';

const likert = [
    { value: 'always', label: 'Siempre' },
    { value: 'almost_always', label: 'Casi siempre' },
    { value: 'sometimes', label: 'Algunas veces' },
    { value: 'almost_never', label: 'Casi nunca' },
    { value: 'never', label: 'Nunca' },
];

const section = (
    numbers: number[],
    gate: SurveySection['gate'] = null,
    title: string | null = null,
): SurveySection => ({
    title,
    instruction: `Instrucción ${numbers[0] ?? 0}`,
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
    options: likert,
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

/** A small Guía I: two events in section I, then two more sections. */
const guideOne: SurveyInstrument = {
    key: 'nom035.guide-i',
    title: 'Acontecimientos traumáticos severos',
    shortTitle: 'Guía I',
    identified: true,
    status: 'pending',
    options: [
        { value: 'yes', label: 'Sí' },
        { value: 'no', label: 'No' },
    ],
    sections: [
        section([1, 2], null, 'I'),
        section([3], null, 'II'),
        section([4, 5], null, 'IV'),
    ],
};

describe('visibleKeys', () => {
    it('asks a gated block only after a yes', () => {
        expect(visibleKeys(guideTwo, {})).toEqual([
            '1',
            '2',
            '3',
            '4',
            'serves_clients',
            'supervises',
        ]);
        expect(
            visibleKeys(guideTwo, { serves_clients: 'yes', supervises: 'no' }),
        ).toEqual([
            '1',
            '2',
            '3',
            '4',
            'serves_clients',
            '5',
            '6',
            'supervises',
        ]);
    });

    it('ends Guía I after a section I with no yes', () => {
        expect(visibleKeys(guideOne, { '1': 'no', '2': 'no' })).toEqual([
            '1',
            '2',
        ]);
        expect(visibleKeys(guideOne, { '1': 'no', '2': 'yes' })).toEqual([
            '1',
            '2',
            '3',
            '4',
            '5',
        ]);
    });
});

describe('isComplete and payload', () => {
    it('is complete once every question asked is answered', () => {
        const answers = {
            '1': 'never',
            '2': 'never',
            '3': 'never',
            '4': 'never',
            serves_clients: 'no',
            supervises: 'yes',
        };

        expect(isComplete(guideTwo, answers)).toBe(false);
        expect(isComplete(guideTwo, { ...answers, '7': 'always' })).toBe(true);
    });

    it('sends only what was asked: answers left from a block later skipped are dropped', () => {
        const answers = {
            '1': 'never',
            '2': 'never',
            '3': 'never',
            '4': 'never',
            serves_clients: 'no',
            '5': 'always',
            supervises: 'no',
        };

        expect(payload(guideTwo, answers)).toEqual({
            '1': 'never',
            '2': 'never',
            '3': 'never',
            '4': 'never',
            serves_clients: 'no',
            supervises: 'no',
        });
    });

    it('takes a Guía I with no event as complete after section I', () => {
        expect(isComplete(guideOne, { '1': 'no', '2': 'no' })).toBe(true);
        expect(payload(guideOne, { '1': 'no', '2': 'no', '3': 'yes' })).toEqual(
            { '1': 'no', '2': 'no' },
        );
    });
});

describe('steps', () => {
    it('asks one question per screen on a phone', () => {
        expect(steps(guideTwo, {}, 1).map((step) => step.keys)).toEqual([
            ['1'],
            ['2'],
            ['3'],
            ['4'],
            ['serves_clients'],
            ['supervises'],
        ]);
    });

    it('asks up to three questions of one section per screen on a computer, each gate on its own', () => {
        expect(
            steps(
                guideTwo,
                { serves_clients: 'yes', supervises: 'yes' },
                3,
            ).map((step) => step.keys),
        ).toEqual([
            ['1', '2', '3'],
            ['4'],
            ['serves_clients'],
            ['5', '6'],
            ['supervises'],
            ['7'],
        ]);
    });

    it('knows the section of each screen', () => {
        const [first, , gate] = steps(guideTwo, {}, 3);

        expect(first?.section).toBe(0);
        expect(gate?.gate?.key).toBe('serves_clients');
        expect(gate?.section).toBe(2);
    });
});

describe('progressOf', () => {
    it('counts by section, never a slow global percentage', () => {
        const answers = { '1': 'never', '2': 'never' };
        const step = steps(guideTwo, answers, 1)[1];

        expect(
            step === undefined ? null : progressOf(guideTwo, answers, step),
        ).toEqual({
            section: 1,
            sections: 4,
            answered: 2,
            inSection: 3,
        });
    });

    it('counts the gate with its block', () => {
        const answers = { serves_clients: 'yes', '5': 'never' };
        const step = steps(guideTwo, answers, 1).find((candidate) =>
            candidate.keys.includes('5'),
        );

        expect(
            step === undefined ? null : progressOf(guideTwo, answers, step),
        ).toEqual({
            section: 3,
            sections: 4,
            answered: 2,
            inSection: 3,
        });
    });
});

describe('storage', () => {
    it('keys what is kept on the device by link and guide', () => {
        expect(storageKey('abc123', 'nom035.guide-iii')).toBe(
            'humetria:survey:abc123:nom035.guide-iii',
        );
    });

    it('reads back only answers it can trust', () => {
        expect(readAnswers('{"1":"never","serves_clients":"no"}')).toEqual({
            '1': 'never',
            serves_clients: 'no',
        });
        expect(readAnswers('{"1":4}')).toEqual({});
        expect(readAnswers('[1,2]')).toEqual({});
        expect(readAnswers('not json')).toEqual({});
        expect(readAnswers(null)).toEqual({});
    });
});

describe('forgetLink', () => {
    it('clears from the device every answer kept for a link that no longer works, and nothing else', () => {
        const kept = new Map([
            ['humetria:survey:abc:nom035.guide-i', '{"1":"yes"}'],
            ['humetria:survey:abc:nom035.guide-iii', '{}'],
            ['humetria:survey:other:nom035.guide-i', '{}'],
            ['appearance', 'dark'],
        ]);
        const storage = {
            get length() {
                return kept.size;
            },
            key: (index: number) => [...kept.keys()][index] ?? null,
            removeItem: (key: string) => {
                kept.delete(key);
            },
        };

        forgetLink(storage, 'abc');

        expect([...kept.keys()]).toEqual([
            'humetria:survey:other:nom035.guide-i',
            'appearance',
        ]);
    });

    it('reads the link from the address', () => {
        expect(tokenFromPath('/survey/abc123')).toBe('abc123');
        expect(tokenFromPath('/survey/a%20b')).toBe('a b');
        expect(tokenFromPath('/admin')).toBeNull();
    });
});
