import { describe, expect, it } from 'vitest';
import {
    answerLabel,
    groupByHeading,
    isAnswer,
    isComplete,
    isUnfavorable,
    pendingQuestions,
    photoFields,
    submission,
    visibleQuestions,
} from '@/lib/telework/answers';
import type { ChecklistState } from '@/lib/telework/answers';
import type { FavorableAnswer, PortalAnswer, PortalQuestion } from '@/types';

function question(
    id: string,
    favorable: FavorableAnswer = 'yes',
    extra: Partial<PortalQuestion> = {},
): PortalQuestion {
    return {
        id,
        heading: 'Espacio',
        text: id,
        favorable,
        sensitive: false,
        ...extra,
    };
}

function state(overrides: Partial<ChecklistState>): ChecklistState {
    return {
        consent: false,
        answers: {},
        notes: {},
        photos: {},
        specialNeeds: '',
        ...overrides,
    };
}

describe('isAnswer', () => {
    it('accepts only the three answers', () => {
        expect(isAnswer('yes')).toBe(true);
        expect(isAnswer('no')).toBe(true);
        expect(isAnswer('not_applicable')).toBe(true);
        expect(isAnswer('maybe')).toBe(false);
        expect(isAnswer('toString')).toBe(false);
    });
});

describe('isUnfavorable', () => {
    const cases: [FavorableAnswer, PortalAnswer, boolean][] = [
        ['yes', 'no', true],
        ['yes', 'yes', false],
        ['yes', 'not_applicable', false],
        ['no', 'yes', true],
        ['no', 'no', false],
        ['no', 'not_applicable', false],
        ['informative', 'yes', false],
        ['informative', 'no', false],
    ];

    it.each(cases)(
        'favorable %s, answered %s: %s',
        (favorable, answer, flagged) => {
            expect(isUnfavorable(favorable, answer)).toBe(flagged);
        },
    );

    it('is false while there is no answer', () => {
        expect(isUnfavorable('yes', undefined)).toBe(false);
    });
});

describe('answerLabel', () => {
    it('names the answers', () => {
        expect(answerLabel('yes')).toBe('Sí');
        expect(answerLabel('no')).toBe('No');
        expect(answerLabel('not_applicable')).toBe('No aplica');
    });
});

describe('visibleQuestions', () => {
    const all = [question('a'), question('b', 'yes', { sensitive: true })];

    it('hides the sensitive ones unless consent was accepted', () => {
        expect(visibleQuestions(all, true)).toHaveLength(2);
        expect(visibleQuestions(all, false).map((q) => q.id)).toEqual(['a']);
        expect(visibleQuestions(all, null).map((q) => q.id)).toEqual(['a']);
    });
});

describe('groupByHeading', () => {
    it('keeps the order of the headings and of their questions', () => {
        const groups = groupByHeading([
            question('a', 'yes', { heading: 'Luz' }),
            question('b', 'yes', { heading: 'Aire' }),
            question('c', 'yes', { heading: 'Luz' }),
        ]);

        expect(groups.map((g) => g.heading)).toEqual(['Luz', 'Aire']);
        expect(groups[0]?.questions.map((q) => q.id)).toEqual(['a', 'c']);
    });
});

describe('pendingQuestions and isComplete', () => {
    const questions = [
        question('a'),
        question('b', 'no'),
        question('c', 'informative'),
    ];
    const answered: Record<string, PortalAnswer> = {
        a: 'yes',
        b: 'no',
        c: 'yes',
    };

    it('lists what still lacks an answer or a note', () => {
        expect(pendingQuestions(questions, state({}))).toEqual(['a', 'b', 'c']);
        expect(
            pendingQuestions(questions, state({ answers: answered })),
        ).toEqual([]);

        const flagged: Record<string, PortalAnswer> = { ...answered, a: 'no' };

        expect(
            pendingQuestions(questions, state({ answers: flagged })),
        ).toEqual(['a']);
        expect(
            pendingQuestions(
                questions,
                state({ answers: flagged, notes: { a: '  ' } }),
            ),
        ).toEqual(['a']);
        expect(
            pendingQuestions(
                questions,
                state({ answers: flagged, notes: { a: 'Cable' } }),
            ),
        ).toEqual([]);
    });

    it('asks the sensitive ones only after consent', () => {
        const withHealth = [
            ...questions,
            question('d', 'yes', { sensitive: true }),
        ];

        expect(
            pendingQuestions(withHealth, state({ answers: answered })),
        ).toEqual([]);
        expect(
            pendingQuestions(
                withHealth,
                state({ answers: answered, consent: true }),
            ),
        ).toEqual(['d']);
    });

    it('is complete only once consent is decided and nothing is pending', () => {
        expect(
            isComplete(questions, state({ answers: answered, consent: null })),
        ).toBe(false);
        expect(isComplete(questions, state({ answers: answered }))).toBe(true);
        expect(
            isComplete(questions, state({ answers: { ...answered, a: 'no' } })),
        ).toBe(false);
        expect(
            isComplete(
                [question('d', 'yes', { sensitive: true })],
                state({ consent: true }),
            ),
        ).toBe(false);
    });
});

describe('submission', () => {
    const questions = [
        question('a'),
        question('b', 'no'),
        question('h', 'yes', { sensitive: true }),
    ];
    const photo = new File(['x'], 'foto.jpg', { type: 'image/jpeg' });

    it('sends notes and photos only for the questions that flag a risk', () => {
        const sent = submission(questions, {
            consent: true,
            answers: { a: 'no', b: 'no', h: 'yes' },
            notes: { a: 'Cable', b: 'Sobra', h: 'Sobra' },
            photos: { a: [photo], b: [photo] },
            specialNeeds: ' Escritorio alto ',
        });

        expect(sent.consent).toBe(true);
        expect(sent.answers).toEqual({ a: 'no', b: 'no', h: 'yes' });
        expect(sent.risk_notes).toEqual({ a: 'Cable' });
        expect(sent.photos).toEqual({ a: [photo] });
        expect(sent.special_needs).toBe('Escritorio alto');
    });

    it('sends the photos of the whole list only with consent', () => {
        const base = {
            answers: {},
            notes: {},
            photos: { general: [photo] },
            specialNeeds: '',
        };

        expect(
            submission(questions, { ...base, consent: true }).photos,
        ).toEqual({ general: [photo] });
        expect(
            submission(questions, { ...base, consent: false }).photos,
        ).toEqual({});
        expect(
            submission(questions, {
                ...base,
                consent: true,
                photos: { general: [] },
            }).photos,
        ).toEqual({});
    });

    it('leaves out the health answers, photos and special needs when consent is declined', () => {
        const sent = submission(questions, {
            consent: false,
            answers: { a: 'no', b: 'no', h: 'yes' },
            notes: { a: 'Cable' },
            photos: { a: [photo] },
            specialNeeds: 'Escritorio alto',
        });

        expect(sent.consent).toBe(false);
        expect(sent.answers).toEqual({ a: 'no', b: 'no' });
        expect(sent.photos).toEqual({});
        expect(sent.special_needs).toBe('');
    });
});

describe('photoFields', () => {
    it('names each photo by its question and position', () => {
        const one = new File(['1'], 'uno.jpg');
        const two = new File(['2'], 'dos.jpg');

        expect(photoFields({ a: [one, two], general: [one] })).toEqual({
            photo_a_0: one,
            photo_a_1: two,
            photo_general_0: one,
        });
        expect(photoFields({})).toEqual({});
    });
});
