import { ANY_STEP } from '@/lib/attendance/next-step';
import { doneMessage, punchMessage } from '@/lib/attendance/punch';
import type { Agreement } from '@/lib/attendance/punch-agreement';
import { isSequence } from '@/lib/attendance/punch-problem';
import type { PunchOutcome, Signer } from '@/lib/attendance/punch';

/** How the last mark went; an error carries its reason, so the screen can say what to do (punchProblem). */
export type PunchFeedback =
    | {
          kind: 'done';
          text: string;
          evidence?: string | null;
          flag?: string | null;
      }
    | { kind: 'error'; text: string; reason?: string; agreement?: Agreement };

export type PunchState = {
    person: Signer | null;
    marks: { type: string; time: string }[] | null;
    feedback: PunchFeedback | null;
};

/**
 * What the screen shows after a mark: only what the server said of the person whose signature it verified, never what
 * it showed before (a second person may use the same browser). Marks only after a mark taken.
 */
export function stateAfter(outcome: PunchOutcome): PunchState {
    if (outcome.kind === 'done') {
        return {
            person: { name: outcome.day.name, next: outcome.day.next },
            marks: outcome.day.marks,
            feedback: {
                kind: 'done',
                text: doneMessage(outcome.day),
                evidence: outcome.day.evidence,
                flag: outcome.day.flag,
            },
        };
    }

    const person =
        outcome.person !== null && isSequence(outcome.reason)
            ? { name: outcome.person.name, next: [...ANY_STEP] }
            : outcome.person;

    return {
        person,
        marks: null,
        feedback: {
            kind: 'error',
            text: punchMessage(outcome.reason),
            reason: outcome.reason,
            ...(outcome.agreement === undefined
                ? {}
                : { agreement: outcome.agreement }),
        },
    };
}

/** The agreement the last answer asks to accept before marking; null when it asks none. */
export function feedbackAgreement(
    feedback: PunchFeedback | null,
): Agreement | null {
    return feedback?.kind === 'error' ? (feedback.agreement ?? null) : null;
}
