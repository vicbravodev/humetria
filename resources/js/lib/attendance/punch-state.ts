import { doneMessage, punchMessage } from '@/lib/attendance/punch';
import type { Agreement } from '@/lib/attendance/punch-agreement';
import type { DayMark, PunchOutcome, Signer } from '@/lib/attendance/punch';

/** How the last mark went; an error carries its reason, so the screen can say what to do (punchProblem). */
export type PunchFeedback =
    | {
          kind: 'done';
          text: string;
          evidence?: string | null;
          flag?: string | null;
      }
    | {
          kind: 'error';
          text: string;
          reason?: string;
          agreement?: Agreement;
          /** The center whose code it was, when it was another center's. */
          center?: string;
      };

export type PunchState = {
    person: Signer | null;
    marks: DayMark[] | null;
    feedback: PunchFeedback | null;
};

/**
 * What the screen shows after a mark: only what the server said of the person whose signature it verified, never what
 * it showed before (a second person may use the same browser), and the steps that proceed for them, also after one that
 * came out of order. Marks only after a mark taken.
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

    return {
        person: outcome.person,
        marks: null,
        feedback: {
            kind: 'error',
            text: punchMessage(outcome.reason),
            reason: outcome.reason,
            ...(outcome.agreement === undefined
                ? {}
                : { agreement: outcome.agreement }),
            ...(outcome.center === undefined ? {} : { center: outcome.center }),
        },
    };
}

/** The agreement the last answer asks to accept before marking; null when it asks none. */
export function feedbackAgreement(
    feedback: PunchFeedback | null,
): Agreement | null {
    return feedback?.kind === 'error' ? (feedback.agreement ?? null) : null;
}
