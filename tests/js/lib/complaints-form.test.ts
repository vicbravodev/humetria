import { describe, expect, it } from 'vitest';
import {
    changesForType,
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
        expect(stepAfter('whom')).toBe('identity');
        expect(stepAfter('identity')).toBe('review');
        expect(stepAfter('review')).toBe('review');
        expect(stepBefore('review')).toBe('identity');
        expect(stepBefore('identity')).toBe('whom');
        expect(stepBefore('intro')).toBe('intro');
    });
});

describe('changesForType', () => {
    it('clears the category and, for the notice of a traumatic event, goes with the name and against nobody in particular', () => {
        expect(changesForType('workplace_violence')).toEqual({
            type: 'workplace_violence',
            category: '',
        });
        expect(changesForType('traumatic_event_notice')).toEqual({
            type: 'traumatic_event_notice',
            category: '',
            identityMode: 'confidential',
            respondentKind: 'unknown',
        });
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

    it('asks for the day a traumatic event happened, and only for that notice', () => {
        const notice = { ...written(30), type: 'traumatic_event_notice' };

        expect(problemWith('story', notice, limits)).toBe(
            'Indique la fecha en que ocurrió.',
        );
        expect(
            problemWith(
                'story',
                { ...notice, occurredOn: '2026-11-10' },
                limits,
            ),
        ).toBeUndefined();
        expect(
            problemWith(
                'story',
                { ...written(30), type: 'workplace_violence' },
                limits,
            ),
        ).toBeUndefined();
    });

    it('asks for the name of who goes with it, and never lets the notice go anonymous', () => {
        const confidential = {
            ...emptyDraft,
            identityMode: 'confidential' as const,
        };

        expect(problemWith('identity', confidential, limits)).toBe(
            'Escriba su nombre.',
        );
        expect(
            problemWith(
                'identity',
                { ...confidential, reporterName: '   ' },
                limits,
            ),
        ).toBe('Escriba su nombre.');
        expect(
            problemWith(
                'identity',
                { ...confidential, reporterName: 'Rosa' },
                limits,
            ),
        ).toBeUndefined();
        expect(problemWith('identity', emptyDraft, limits)).toBeUndefined();
        expect(
            problemWith(
                'identity',
                { ...emptyDraft, type: 'traumatic_event_notice' },
                limits,
            ),
        ).toContain('necesita su nombre');
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
            reporter_name: '',
            reporter_contact: '',
            involved_text: '',
            website: '',
        });
    });

    it('sends the name and the contact only when the person chose to give them', () => {
        const draft = {
            ...emptyDraft,
            type: 'workplace_violence',
            reporterName: ' Rosa ',
            reporterContact: ' 442 000 ',
            involvedText: 'Luis',
        };

        expect(payload(draft, 't')).toMatchObject({
            identity_mode: 'anonymous',
            reporter_name: '',
            reporter_contact: '',
            involved_text: '',
        });
        expect(
            payload({ ...draft, identityMode: 'confidential' }, 't'),
        ).toMatchObject({
            identity_mode: 'confidential',
            reporter_name: 'Rosa',
            reporter_contact: '442 000',
            involved_text: '',
        });
    });

    it('sends the people involved only with the notice of a traumatic event', () => {
        const notice = {
            ...emptyDraft,
            type: 'traumatic_event_notice',
            identityMode: 'confidential' as const,
            reporterName: 'Rosa',
            involvedText: ' Luis ',
        };

        expect(payload(notice, 't').involved_text).toBe('Luis');
    });
});
