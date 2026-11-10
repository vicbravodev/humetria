export type LeaveRequestStatus =
    | 'requested'
    | 'approved'
    | 'rejected'
    | 'cancelled'
    | 'taken';

/** A vacation period of the person that still has days. */
export type LeavePeriod = {
    /** The year of the anniversary that gave it. */
    year: number;
    granted: number;
    available: number;
    /** Y-m-d: the last day of the six months to enjoy it. */
    enjoyBy: string;
    mayPrescribe: boolean;
};

/** A request of the person, as they read it in their portal. */
export type PortalLeaveRequest = {
    id: string;
    /** The name of the leave; null for vacations. */
    type: string | null;
    startsOn: string;
    endsOn: string;
    workingDays: number;
    status: LeaveRequestStatus;
    statusLabel: string;
    /** Whether they had the days when they asked; null for a leave, which uses none. */
    hadBalance: boolean | null;
    rejectionReason: string | null;
    canCancel: boolean;
};

export type LeaveTypeOption = { id: string; name: string };

/** What a first and a last day would be as a request, before sending it. */
export type LeavePreview = {
    workingDays: number;
    /** Whether the person has the days; null for a leave, which uses none. */
    hadBalance: boolean | null;
    shortBy: number;
    /** Whether to remind them of the twelve days in a row the law gives them. */
    continuousNotice: boolean;
};
