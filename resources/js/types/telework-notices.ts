export type NoticeKindValue =
    | 'condition_change'
    | 'address_change'
    | 'work_risk';

/** A notice the person sent, as their portal lists it: only that it was received, or reviewed. */
export type PortalNotice = {
    id: string;
    /** The kind, worded. */
    kind: string;
    /** Y-m-d. */
    writtenOn: string;
    status: 'Recibido' | 'Revisado';
};

/** A notice as RH and the Comisión read it, with the review if there is one. */
export type NoticeRow = {
    id: string;
    person: string;
    jobTitle: string;
    workCenter: string;
    kindValue: NoticeKindValue;
    /** The kind, worded. */
    kind: string;
    /** Y-m-d. */
    writtenOn: string;
    occurredOn: string;
    description: string;
    traumaticEvent: boolean;
    temporary: boolean;
    /** The new workplace of an address change, in one line. */
    newAddress: string | null;
    submittedOn: string;
    reviewedOn: string | null;
    reviewNote: string | null;
    canReview: boolean;
    /** The way into Guía I for a traumatic event, only for who may launch it. */
    trauma: { workCenter: string; employee: string } | null;
};

export type NoticeFilters = {
    center: { value: string; label: string } | null;
};

/** A notice on the page of the person who wrote it. */
export type TeleworkerNotice = {
    id: string;
    kind: string;
    writtenOn: string;
    occurredOn: string;
    description: string;
    status: 'Recibido' | 'Revisado';
    reviewedOn: string | null;
};
