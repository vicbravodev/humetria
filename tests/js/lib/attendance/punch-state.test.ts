import { describe, expect, it } from 'vitest';
import { feedbackAgreement, stateAfter } from '@/lib/attendance/punch-state';

const day = {
    name: 'Bob Ruiz',
    marks: [{ type: 'start', time: '09:00' }],
    next: ['break_start', 'end'],
    flag: null,
    evidence: 'strong',
};

describe('stateAfter, a code of another center', () => {
    it('keeps the center whose code it was, for the problem to name it', () => {
        expect(
            stateAfter({
                kind: 'failed',
                reason: 'qr_other_center',
                person: { name: 'Ana', next: ['start'] },
                center: 'Oficinas',
            }).feedback,
        ).toMatchObject({ reason: 'qr_other_center', center: 'Oficinas' });
    });
});

describe('stateAfter', () => {
    it('shows the person who just signed, their day and their next steps', () => {
        expect(stateAfter({ kind: 'done', day })).toEqual({
            person: { name: 'Bob Ruiz', next: ['break_start', 'end'] },
            marks: day.marks,
            feedback: {
                kind: 'done',
                text: 'Listo: se registró su entrada a las 09:00.',
                evidence: 'strong',
                flag: null,
            },
        });
    });

    it('offers only the steps that proceed after a mark out of sequence, said, and shows no marks (hallazgo 27)', () => {
        const state = stateAfter({
            kind: 'failed',
            reason: 'day_not_open',
            person: { name: 'Bob Ruiz', next: ['start'] },
        });

        expect(state.person).toEqual({ name: 'Bob Ruiz', next: ['start'] });
        expect(state.marks).toBeNull();
        expect(state.feedback).toEqual({
            kind: 'error',
            text: 'Primero marque su entrada.',
            reason: 'day_not_open',
        });
    });

    it.each(['day_already_open', 'already_on_break', 'not_on_break'])(
        'offers the steps of the person who signed after %s',
        (reason) => {
            expect(
                stateAfter({
                    kind: 'failed',
                    reason,
                    person: { name: 'Bob', next: ['break_end'] },
                }).person?.next,
            ).toEqual(['break_end']);
        },
    );

    it('keeps the agreement to accept with its refusal, for the screen to show it', () => {
        expect(
            stateAfter({
                kind: 'failed',
                reason: 'agreement_not_accepted',
                person: { name: 'Bob Ruiz', next: ['start'] },
                agreement: { version: 2, body: 'Texto' },
            }).feedback,
        ).toEqual({
            kind: 'error',
            text: 'Para marcar, primero acepte el acuerdo de registro electrónico.',
            reason: 'agreement_not_accepted',
            agreement: { version: 2, body: 'Texto' },
        });
    });

    it('keeps the steps the server gave after a rejection of the policy', () => {
        expect(
            stateAfter({
                kind: 'failed',
                reason: 'outside_zone',
                person: { name: 'Bob Ruiz', next: ['start'] },
            }).person,
        ).toEqual({ name: 'Bob Ruiz', next: ['start'] });
    });

    it('forgets whoever was shown when the signature names nobody', () => {
        expect(
            stateAfter({
                kind: 'failed',
                reason: 'not_verified',
                person: null,
            }),
        ).toEqual({
            person: null,
            marks: null,
            feedback: {
                kind: 'error',
                text: expect.stringContaining('No se pudo verificar'),
                reason: 'not_verified',
            },
        });
    });
});

describe('feedbackAgreement', () => {
    it('is the agreement to accept of a refusal, and null otherwise', () => {
        const agreement = { version: 2, body: 'Texto' };

        expect(
            feedbackAgreement({ kind: 'error', text: 'x', agreement }),
        ).toEqual(agreement);
        expect(feedbackAgreement({ kind: 'error', text: 'x' })).toBeNull();
        expect(feedbackAgreement({ kind: 'done', text: 'Listo.' })).toBeNull();
        expect(feedbackAgreement(null)).toBeNull();
    });
});
