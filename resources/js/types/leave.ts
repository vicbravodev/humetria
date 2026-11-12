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
    /** Active people of the company. */
    people: number;
    /** Periods that still hold days. */
    periods: number;
    missingStartDate: number;
    statementsUnacknowledged: number;
};

export type LeaveSummaryView = 'out' | 'windows' | 'prescribable';

/** Whether the days cover a request and how many would be left (negative: how many are missing); RH only. */
export type LeaveCoverage = { fits: boolean; leftAfter: number };

/** The teammates of the same manager away on the days of a request: a count per day and their names, never why. */
export type LeaveTeam = {
    days: { date: string; away: number }[];
    names: string[];
};

/** How many people are out on a day (Y-m-d), without names. */
export type LeaveAheadDay = { date: string; away: number };

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
    /** Y-m-d: the day it was asked, since when it waits. */
    requestedOn: string;
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
    /** Only for RH, for vacations that wait. */
    coverage: LeaveCoverage | null;
    /** Only for what waits. */
    team: LeaveTeam | null;
};

export type LeaveBalanceTab = 'all' | 'missing' | 'statements' | 'terminations';

/** How the list of balances is narrowed, as the server sends it back. */
export type LeaveBalanceFilters = { q: string; tab: LeaveBalanceTab };

/** A person in the list of balances: days only, never an amount. */
export type LeaveBalanceRow = {
    id: string;
    name: string;
    /** False for who left. */
    active: boolean;
    /** The name of the policy their vacations follow; «Ley» when the company has none for them. */
    policy: string;
    /** What they can still ask for; below zero when they were given days in advance. */
    available: number;
    /** The days they were given in advance. */
    advanced: number;
    /** The days given in the periods that still hold days: the whole the bar measures; null when not known. */
    granted: number | null;
    /** Y-m-d: when their next period starts; null while they have no start date. */
    nextAnniversary: string | null;
};

/** A period in the record of a person, newest first. */
export type LeaveRecordPeriod = {
    id: string;
    year: number;
    anniversaryOn: string;
    enjoyBy: string;
    prescribesOn: string;
    granted: number;
    available: number;
    /** Past its eighteen months with days still in it: marked, never taken off. */
    mayPrescribe: boolean;
};

/** A movement of the ledger of a person. The note only reaches whoever may manage vacations. */
export type LeaveLedgerEntry = {
    id: string;
    kind: string;
    kindLabel: string;
    tranche: 'statutory' | 'extra';
    /** With its sign: below zero leaves the balance. */
    days: number;
    /** The year of the period it moved; null for days given in advance. */
    period: number | null;
    note: string | null;
    /** Y-m-d. */
    on: string;
};

/** A request in the record of a person, with who answered it and when. */
export type LeaveRecordRequest = {
    id: string;
    /** The name of the leave for whoever manages vacations, «Permiso» for a viewer; null for vacations. */
    type: string | null;
    startsOn: string;
    endsOn: string;
    workingDays: number;
    status: LeaveRequestStatus;
    statusLabel: string;
    /** The manager, or «RH»; null while nobody answered. */
    decidedBy: string | null;
    decidedOn: string | null;
    note: string | null;
};

export type LeaveRecordStatement = {
    id: string;
    year: number;
    issuedOn: string;
    acknowledgedOn: string | null;
};

/** What a person who left is owed, in days. */
export type LeaveRecordTermination = {
    id: string;
    terminatedOn: string;
    pendingDays: number;
    proportionalDays: number;
    settledOn: string | null;
    canSettle: boolean;
};

export type LeaveRecordPerson = {
    id: string;
    name: string;
    active: boolean;
    jobTitle: string;
    area: string;
    policy: string;
    startedOn: string | null;
};

/** A stretch of days a person is away in the month of the calendar, cut to it. */
export type LeaveCalendarStretch = {
    /** Y-m-d. */
    from: string;
    to: string;
    /** Vacations or leave: never which leave. */
    kind: 'vacation' | 'leave';
    /** Still waits for an answer; only whoever manages vacations gets these. */
    pending: boolean;
};

export type LeaveCalendarPerson = {
    id: string;
    name: string;
    absences: LeaveCalendarStretch[];
};

/** How the calendar is narrowed, as the server sends it back. */
export type LeaveCalendarFilters = {
    center: { value: string; label: string } | null;
    area: { value: string; label: string } | null;
};
