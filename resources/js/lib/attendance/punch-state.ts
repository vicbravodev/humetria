import { ANY_STEP } from '@/lib/attendance/next-step';
import { doneMessage, punchMessage } from '@/lib/attendance/punch';
import type { PunchOutcome, Signer } from '@/lib/attendance/punch';

export type PunchFeedback = { kind: 'done' | 'error'; text: string };

export type PunchState = {
    person: Signer | null;
    marks: { type: string; time: string }[] | null;
    feedback: PunchFeedback | null;
};

/** The rejections of the sequence: whoever tapped may not be who the screen showed, so every step is offered again. */
const SEQUENCE = new Set([
    'day_already_open',
    'day_not_open',
    'already_on_break',
    'not_on_break',
]);

/**
 * What the screen shows after a mark: only what the server said of the person whose signature it verified, never what
 * it showed before (a second person may use the same browser). Marks only after a mark taken.
 */
export function stateAfter(outcome: PunchOutcome): PunchState {
    if (outcome.kind === 'done') {
        return {
            person: { name: outcome.day.name, next: outcome.day.next },
            marks: outcome.day.marks,
            feedback: { kind: 'done', text: doneMessage(outcome.day) },
        };
    }

    const person =
        outcome.person !== null && SEQUENCE.has(outcome.reason)
            ? { name: outcome.person.name, next: [...ANY_STEP] }
            : outcome.person;

    return {
        person,
        marks: null,
        feedback: { kind: 'error', text: punchMessage(outcome.reason) },
    };
}
