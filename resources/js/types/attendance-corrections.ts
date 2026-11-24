import type { Paginated } from './demos';

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
    /** What the person wrote; null for whoever only views (it may be medical). */
    reason: string | null;
    /** Days the request waits since it was asked. */
    ageDays: number;
    status: CorrectionStatus;
    note: string | null;
};

/** The tabs of the queue: the requests by state, the marks made without a connection to verify and the deliveries not accepted. */
export type CorrectionView = CorrectionStatus | 'offline' | 'not_accepted';

export type CorrectionCounts = Record<CorrectionView, number>;

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

/** What RH is warned of in a mark made without a connection (ReviewFlag). */
export type OfflineFlag =
    | 'wide_interval'
    | 'old_window'
    | 'no_position'
    | 'outside_zone'
    | 'declared_adjusted';

/** A row of the tab «Sin conexión»: a mark that counts and no one reviewed. Never a position, a key, a ticket nor a device. */
export type OfflineMarkRow = {
    /** The id of the mark: what the actions of review take. */
    id: string;
    person: { name: string; jobTitle: string };
    type: string;
    /** The time counted, local to where it was taken («Y-m-d H:i»). */
    countedAt: string;
    /** How the server bounded it: by the window of a QR, or by the interval its ticket and neighbours prove. */
    basis: 'qr_window' | 'declared_interval';
    flags: OfflineFlag[];
    /** Days since it arrived. */
    ageDays: number;
};

/** The mark in view: its row, every hour named and who signed it. */
export type OfflineMarkDetail = OfflineMarkRow & {
    declaredAt: string;
    /** Since when the person had no connection: the issue of the ticket they marked with. */
    ticketIssuedAt: string;
    /** «Not before»: only with a QR. */
    windowStart: string | null;
    receivedAt: string;
    /** The phone's own clock when it delivered: a hint, never proof; null when it did not come. */
    phoneClockAt: string | null;
    /** The kind of key that signed it (`passkey` or `device_key`), never which. */
    signer: string;
    /** What the person wrote: only for whoever manages attendance (it may be medical). */
    justification: string | null;
    /** The time the dialog of «Es incorrecta» proposes: the declared one when it differs from the counted one. */
    proposedAt: string;
};

/** A row of the tab «No aceptadas»: a delivery kept as rejected, read-only. */
export type RejectedOfflineRow = {
    id: string;
    person: { name: string; jobTitle: string };
    type: string;
    declaredAt: string;
    reason: string | null;
    receivedAt: string;
};

type QueueProps = {
    counts: CorrectionCounts;
    canResolve: boolean;
};

/** What the page of Correcciones receives: by the tab it is in, the requests or the marks made without a connection. */
export type CorrectionsProps =
    | (QueueProps & {
          status: CorrectionStatus;
          requests: Paginated<CorrectionRow>;
          filters: { q: string };
          selectedId: string | null;
          detail: CorrectionDetail | null;
      })
    | (QueueProps & {
          status: 'offline';
          offlineMarks: Paginated<OfflineMarkRow>;
          filters: { q: string; flags: string };
          selectedId: string | null;
          detail: OfflineMarkDetail | null;
      })
    | (QueueProps & {
          status: 'not_accepted';
          offlineMarks: Paginated<RejectedOfflineRow>;
          filters: { q: string; flags: string };
          selectedId: null;
          detail: null;
      });
