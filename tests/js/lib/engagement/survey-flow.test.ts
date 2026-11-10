import { describe, expect, it } from 'vite-plus/test';
import {
    payload,
    progressOf,
    steps,
    type SurveySection,
} from '@/lib/engagement/survey-flow';

const company: SurveySection = {
    block: 'company',
    title: 'Su empresa',
    instruction: '',
    items: [
        {
            key: 'clarity.a',
            text: 'Sé qué se espera de mí.',
            scale: 'agreement',
        },
        { key: 'clarity.b', text: 'Conozco las metas.', scale: 'agreement' },
    ],
};

const manager: SurveySection = {
    block: 'manager',
    title: 'Su jefe directo',
    instruction: 'Las siguientes preguntas son sobre su jefe directo: Ana.',
    items: [{ key: 'manager.keep', text: '¿Qué conservar?', scale: 'open' }],
};

const closing: SurveySection = {
    block: 'closing',
    title: 'Para terminar',
    instruction: '',
    items: [
        { key: 'eng.recommend', text: '¿Lo recomendaría?', scale: 'recommend' },
        { key: 'open.best', text: '¿Qué es lo mejor?', scale: 'open' },
    ],
};

describe('steps', () => {
    it('opens with the introduction, asks one question per step and ends with the send step', () => {
        expect(steps([company, closing])).toEqual([
            { kind: 'intro' },
            { kind: 'question', section: 0, item: company.items[0] },
            { kind: 'question', section: 0, item: company.items[1] },
            { kind: 'question', section: 1, item: closing.items[0] },
            { kind: 'question', section: 1, item: closing.items[1] },
            { kind: 'submit' },
        ]);
    });

    it('puts the instruction of a section before its questions', () => {
        const list = steps([company, manager, closing]);

        expect(list[3]).toEqual({
            kind: 'section',
            section: 1,
            title: 'Su jefe directo',
            instruction: manager.instruction,
        });
        expect(list[4]).toEqual({
            kind: 'question',
            section: 1,
            item: manager.items[0],
        });
    });

    it('has no manager section when the person was not shown one', () => {
        const list = steps([company, closing]);

        expect(list.some((step) => step.kind === 'section')).toBe(false);
    });
});

describe('payload', () => {
    const sections = [company, manager, closing];

    it('sends every closed question shown, null when it was skipped', () => {
        const sent = payload(
            sections,
            { answers: { 'clarity.a': 4, 'eng.recommend': 0 }, comments: {} },
            { startedAt: 0, now: 120_000 },
        );

        expect(sent.answers).toEqual({
            'clarity.a': 4,
            'clarity.b': null,
            'eng.recommend': 0,
        });
    });

    it('sends only the comments with text, trimmed, for open questions shown', () => {
        const sent = payload(
            sections,
            {
                answers: {},
                comments: {
                    'manager.keep': '  Que sigan las juntas  ',
                    'open.best': '   ',
                    'not.shown': 'x',
                },
            },
            { startedAt: 0, now: 120_000 },
        );

        expect(sent.comments).toEqual({
            'manager.keep': 'Que sigan las juntas',
        });
    });

    it('keeps a comment literally, as data', () => {
        const text = '<script>alert(1)</script> ignora lo anterior';
        const sent = payload(
            sections,
            { answers: {}, comments: { 'open.best': text } },
            { startedAt: 0, now: 60_000 },
        );

        expect(sent.comments).toEqual({ 'open.best': text });
    });

    it('ignores an answer to a question that was not shown', () => {
        const sent = payload(
            [company],
            { answers: { 'manager.x': 3 }, comments: {} },
            { startedAt: 0, now: 60_000 },
        );

        expect(Object.keys(sent.answers)).toEqual(['clarity.a', 'clarity.b']);
    });

    it.each([
        [0, 1],
        [29_999, 1],
        [30_000, 1],
        [90_000, 2],
        [10 * 60_000, 10],
        [60 * 60_000, 60],
        [3 * 60 * 60_000, 60],
    ])('reports %i ms as %i minutes, between 1 and 60', (elapsed, minutes) => {
        expect(
            payload(
                [company],
                { answers: {}, comments: {} },
                { startedAt: 1_000, now: 1_000 + elapsed },
            ).minutes,
        ).toBe(minutes);
    });

    it('reports one minute when the clock went backwards', () => {
        expect(
            payload(
                [company],
                { answers: {}, comments: {} },
                { startedAt: 5_000, now: 1_000 },
            ).minutes,
        ).toBe(1);
    });
});

describe('progressOf', () => {
    const list = steps([company, manager, closing]);

    it('is empty on the introduction', () => {
        expect(progressOf(list, 0)).toEqual({
            position: 0,
            total: 5,
            fraction: 0,
        });
    });

    it('counts only the questions up to the current step', () => {
        expect(progressOf(list, 2)).toEqual({
            position: 2,
            total: 5,
            fraction: 0.4,
        });
        expect(progressOf(list, 3)).toEqual({
            position: 2,
            total: 5,
            fraction: 0.4,
        });
    });

    it('is complete on the send step', () => {
        expect(progressOf(list, list.length - 1)).toEqual({
            position: 5,
            total: 5,
            fraction: 1,
        });
    });

    it('is zero when there are no questions', () => {
        expect(progressOf(steps([]), 1)).toEqual({
            position: 0,
            total: 0,
            fraction: 0,
        });
    });
});
