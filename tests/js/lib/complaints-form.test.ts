import { describe, expect, it } from 'vitest';
import {
    emptyDraft,
    payload,
    problemWith,
    stepAfter,
    stepBefore,
} from '@/lib/complaints/form';

const limits = { min: 20, max: 5000 };
const written = (length: number) => ({
    ...emptyDraft,
    description: 'a'.repeat(length),
});

describe('stepAfter and stepBefore', () => {
    it('move one step and stop at both ends', () => {
        expect(stepAfter('intro')).toBe('type');
        expect(stepAfter('whom')).toBe('review');
        expect(stepAfter('review')).toBe('review');
        expect(stepBefore('review')).toBe('whom');
        expect(stepBefore('intro')).toBe('intro');
    });
});

describe('problemWith', () => {
    it('asks for the type before leaving the step that chooses it', () => {
        expect(problemWith('type', emptyDraft, limits)).toBe(
            'Elija qué quiere informar.',
        );
        expect(
            problemWith(
                'type',
                { ...emptyDraft, type: 'workplace_violence' },
                limits,
            ),
        ).toBeUndefined();
    });

    it('checks the story at its exact limits', () => {
        expect(problemWith('story', written(19), limits)).toContain('20');
        expect(problemWith('story', written(20), limits)).toBeUndefined();
        expect(problemWith('story', written(5000), limits)).toBeUndefined();
        expect(problemWith('story', written(5001), limits)).toContain('5000');
    });

    it('does not count the spaces around the story', () => {
        expect(
            problemWith(
                'story',
                { ...emptyDraft, description: `  ${'a'.repeat(19)}  ` },
                limits,
            ),
        ).toContain('20');
    });

    it('asks of whom it is before leaving that step', () => {
        expect(problemWith('whom', emptyDraft, limits)).toContain('de quién');
        expect(
            problemWith(
                'whom',
                { ...emptyDraft, respondentKind: 'unknown' },
                limits,
            ),
        ).toBeUndefined();
    });

    it('asks nothing on the steps that only read or review', () => {
        expect(problemWith('intro', emptyDraft, limits)).toBeUndefined();
        expect(problemWith('review', emptyDraft, limits)).toBeUndefined();
    });
});

describe('payload', () => {
    it('sends what the server validates, anonymous, with the honeypot empty', () => {
        expect(
            payload(
                {
                    ...emptyDraft,
                    type: 'unfavorable_practice',
                    description: '  Mucho trabajo  ',
                    workCenter: { value: 'center-id', label: 'Planta' },
                    respondentKind: 'coworker',
                    respondentText: ' Luis ',
                },
                'ticket-1',
            ),
        ).toEqual({
            ticket: 'ticket-1',
            type: 'unfavorable_practice',
            category: '',
            description: 'Mucho trabajo',
            occurred_on: '',
            work_center_id: 'center-id',
            respondent_kind: 'coworker',
            respondent_text: 'Luis',
            identity_mode: 'anonymous',
            website: '',
        });
    });
});
