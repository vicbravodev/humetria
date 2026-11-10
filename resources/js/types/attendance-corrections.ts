/** Where a person's request to correct their record stands. */
export type CorrectionStatus = 'pending' | 'approved' | 'rejected';

/** A row of RH's queue of correction requests: who, what they ask, why, and RH's note once resolved. Never a position. */
export type CorrectionRow = {
    id: string;
    person: { name: string; jobTitle: string };
    type: string;
    /** The time asked, local («Y-m-d H:i»). */
    requestedAt: string;
    /** The mark's time before the correction, local; null for a mark the person forgot. */
    previousAt: string | null;
    reason: string;
    /** Days the request waits since it was asked. */
    ageDays: number;
    status: CorrectionStatus;
    note: string | null;
};

export type CorrectionCounts = Record<CorrectionStatus, number>;

/** A mark of the day as local «H:i», and whether it falls after midnight (the day it belongs to began before). */
export type CorrectionMark = { type: string; time: string; nextDay: boolean };

/** What approving a pending request would do (CorrectionPreview): said before RH decides. */
export type CorrectionEffect = {
    /** False when the approval would be refused for leaving the day out of order. */
    sequenceOk: boolean;
    afterApproval: 'complete' | 'missing_end';
    /** The Mondays of the confirmed weeks that would reopen. */
    reopensWeeks: string[];
    noSchedule: boolean;
    restDay: boolean;
};

/** The request in view: its row and, while pending, its day in the record and what approving it would do. */
export type CorrectionDetail = CorrectionRow & {
    day: {
        date: string;
        marks: CorrectionMark[];
        asked: CorrectionMark;
    } | null;
    effect: CorrectionEffect | null;
};
