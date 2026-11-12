import { describe, expect, it } from 'vite-plus/test';
import { repeatsEvaluationStep } from '@/lib/first-steps/dashboard';
import type { FirstStepTool, FirstSteps } from '@/types/first-steps';

const make = (
    tool: FirstStepTool | null,
    over: { done?: boolean; hidden?: boolean } = {},
): FirstSteps => ({
    steps: [
        { key: 'work_centers', done: true, href: null, tool: null },
        { key: 'evaluation', done: over.done ?? false, href: null, tool },
    ],
    welcomed: true,
    hidden: over.hidden ?? false,
    others: [],
});

describe('repeatsEvaluationStep', () => {
    it('does not repeat without first steps', () =>
        expect(repeatsEvaluationStep(null, 'questions')).toBe(false));
    it('does not repeat when hidden', () =>
        expect(
            repeatsEvaluationStep(
                make('nom037', { hidden: true }),
                'questions',
            ),
        ).toBe(false));
    it('does not repeat when the step is done', () =>
        expect(
            repeatsEvaluationStep(make('nom037', { done: true }), 'questions'),
        ).toBe(false));
    it('does not repeat without an evaluation step', () => {
        const none: FirstSteps = {
            steps: [],
            welcomed: true,
            hidden: false,
            others: [],
        };
        expect(repeatsEvaluationStep(none, 'questions')).toBe(false);
    });
    it('does not repeat without a recommendation', () =>
        expect(repeatsEvaluationStep(make('nom037'), null)).toBe(false));

    it.each<[FirstStepTool | null, boolean]>([
        ['nom037', true],
        ['seating', true],
        ['nom035', false],
        ['attendance', false],
        [null, false],
    ])('questions with tool %s gives %s', (tool, expected) => {
        expect(repeatsEvaluationStep(make(tool), 'questions')).toBe(expected);
    });

    it('repeats when the recommended norm is the step tool', () =>
        expect(repeatsEvaluationStep(make('nom035'), 'nom035')).toBe(true));
    it('does not repeat for a different norm', () =>
        expect(repeatsEvaluationStep(make('nom035'), 'nom037')).toBe(false));
});
