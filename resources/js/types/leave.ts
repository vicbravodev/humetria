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

/** A request that waits for the manager's answer, with where its days would come from today. */
export type TeamLeaveRequest = {
    id: string;
    /** Who asked. */
    name: string;
    /** The name of the leave, which whoever approves does see; null for vacations. */
    type: string | null;
    startsOn: string;
    endsOn: string;
    workingDays: number;
    note: string | null;
    /** The days each period would give, from the oldest. */
    periods: { year: number; days: number }[];
    /** The days the person does not have. */
    shortBy: number;
    allowsAdvance: boolean;
    canApprove: boolean;
};

/** What the annual statement of a period says (LFT art. 81), in days and dates; the premium is a percent, never an amount. */
export type StatementSheetData = {
    company: string;
    name: string;
    /** "15 de marzo de 2024": since when their seniority counts. */
    startedOn: string;
    years: number;
    days: number;
    enjoyFrom: string;
    enjoyBy: string;
    premiumPercent: number;
    issuedOn: string;
    /** When they confirmed they received it; null while they have not. */
    acknowledgedOn: string | null;
};

/** A statement the person has yet to confirm. */
export type PendingStatement = { id: string; year: number };
