/** Where a person's request to correct their record stands. */
export type CorrectionStatus = 'pending' | 'approved' | 'rejected';

/** A row of RH's queue of correction requests: who, what they ask, why, and RH's note once resolved. Never a position. */
export type CorrectionRow = {
    id: string;
    person: { name: string; jobTitle: string };
    type: string;
    /** The time asked, local («Y-m-d H:i»). */
    requestedAt: string;
    /** The mark's time now, local; null for a mark the person forgot. */
    currentAt: string | null;
    reason: string;
    /** Days the request waits since it was asked. */
    ageDays: number;
    status: CorrectionStatus;
    note: string | null;
};

export type CorrectionCounts = Record<CorrectionStatus, number>;
