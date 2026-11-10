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

/** What RH reads first in Vacaciones: people and days, never an amount. */
export type LeaveSummary = {
    outToday: number;
    outThisWeek: number;
    /** Requests that wait for the manager. */
    pending: number;
    /** Requests the manager did not answer in time, now in RH's hands. */
    escalated: number;
    windowsClosing: number;
    /** Days still in the balance past their eighteen months: the company decides whether to oppose the prescription. */
    prescribableDays: number;
    liabilityDays: number;
    missingStartDate: number;
    statementsUnacknowledged: number;
};

export type LeaveSummaryView = 'out' | 'windows' | 'prescribable';

/** A row of the detail under the summary: who is out, or whose period is about to close or past its eighteen months. */
export type LeaveSummaryRow = {
    id: string;
    name: string;
    /** "Vacaciones" or "Permiso" for who is out (never which leave); "Periodo 2025" for a period. */
    label: string;
    days: number;
    startsOn: string;
    endsOn: string;
};

export type LeaveQueueTab =
    | 'attend'
    | 'waiting'
    | 'approved'
    | 'rejected'
    | 'cancelled'
    | 'taken';

/** How RH's queue is narrowed, as the server sends it back: a chosen center or area comes with its name. */
export type LeaveQueueFilters = {
    q: string;
    center: { value: string; label: string } | null;
    area: { value: string; label: string } | null;
    tab: LeaveQueueTab;
};

/** A request in RH's queue. The note, the reason of a rejection and which leave it was only reach whoever may change it. */
export type LeaveQueueRequest = {
    id: string;
    name: string;
    /** Whose it was to answer; null when nobody active was above the person. */
    manager: string | null;
    /** The name of the leave for whoever manages vacations, "Permiso" for a viewer; null for vacations. */
    type: string | null;
    startsOn: string;
    endsOn: string;
    workingDays: number;
    status: LeaveRequestStatus;
    statusLabel: string;
    /** Whether they had the days when they asked; null for a leave. */
    hadBalance: boolean | null;
    shortBy: number;
    /** The years of the periods its days are charged to. */
    periods: number[];
    /** Why it reached RH, while it waits. */
    reason: string | null;
    recordedByHr: boolean;
    note: string | null;
    rejectionReason: string | null;
    canDecide: boolean;
    canCancel: boolean;
};
