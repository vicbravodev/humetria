import { describe, expect, it } from 'vitest';
import { ANY_STEP } from '@/lib/attendance/next-step';
import { stateAfter } from '@/lib/attendance/punch-state';

const day = {
    name: 'Bob Ruiz',
    marks: [{ type: 'start', time: '09:00' }],
    next: ['break_start', 'end'],
    flag: null,
};

describe('stateAfter', () => {
    it('shows the person who just signed, their day and their next steps', () => {
        expect(stateAfter({ kind: 'done', day })).toEqual({
            person: { name: 'Bob Ruiz', next: ['break_start', 'end'] },
            marks: day.marks,
            feedback: {
                kind: 'done',
                text: 'Listo: se registró su entrada a las 09:00.',
            },
        });
    });

    it('offers every step after a mark out of sequence, so nobody is stuck, and shows no marks', () => {
        const state = stateAfter({
            kind: 'failed',
            reason: 'day_not_open',
            person: { name: 'Bob Ruiz', next: ['start'] },
        });

        expect(state.person).toEqual({ name: 'Bob Ruiz', next: ANY_STEP });
        expect(state.marks).toBeNull();
        expect(state.feedback).toEqual({
            kind: 'error',
            text: 'Primero marque su entrada.',
        });
    });

    it.each(['day_already_open', 'already_on_break', 'not_on_break'])(
        'offers every step after %s',
        (reason) => {
            expect(
                stateAfter({
                    kind: 'failed',
                    reason,
                    person: { name: 'Bob', next: ['start'] },
                }).person?.next,
            ).toEqual(ANY_STEP);
        },
    );

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
            },
        });
    });
});
