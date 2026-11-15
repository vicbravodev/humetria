/** What the law gives, as the first card of the policies: days and a percent, never an amount. */
export type LeaveLaw = { firstYearDays: number; premiumPercent: number };

/** A policy of the company as a card. */
export type LeavePolicyCard = {
    id: string;
    name: string;
    premiumPercent: number;
    /** What it gives in the first year: its tier for it, or the law's. */
    firstYearDays: number;
    /** The days that come every year on top (birthday, personal days). */
    fixedDays: number;
    /** How many targets (the company, a group, a grade) it is assigned to. */
    assigned: number;
    /** How many active people follow it, by the most specific assignment. */
    people: number;
    /** The days it gives in years 1 to 6 of service (the sixth stands for 6 to 10), tiers, law and fixed days together. */
    byYear: number[];
    archived: boolean;
};

export type LeaveWorkWeek = 'mon_fri' | 'mon_sat';

/** A row of a policy: the days of a range of years, or days that come every year. */
export type LeavePolicyRowData = {
    kind: 'tier' | 'fixed';
    label: string | null;
    yearFrom: number | null;
    yearTo: number | null;
    days: number;
};

export type LeavePolicyData = {
    id: string;
    name: string;
    premiumPercent: number;
    workWeek: LeaveWorkWeek;
    allowsAdvance: boolean;
    extraExpiresMonths: number | null;
    extraCarryoverCapDays: number | null;
    rows: LeavePolicyRowData[];
};

/** The policy (an id) the company and each staff group follow; null where the law follows. */
export type LeaveAssignments = {
    default: string | null;
    unionized: string | null;
    trusted: string | null;
};

/** A job grade with a policy of its own. */
export type LeaveGradeAssignment = {
    id: string;
    label: string;
    policyId: string;
};

export type LeavePolicyOption = { value: string; label: string };

export type LeaveSettingsTab = 'types' | 'holidays' | 'week';

/** A leave type of the settings; the ones of the law cannot be changed or archived. */
export type LeaveTypeRow = {
    id: string;
    name: string;
    paid: boolean;
    statutory: boolean;
    archived: boolean;
};

export type LeaveHolidayRow = { id: string; date: string; name: string };

/** Since which day the periods are granted (never edited) and the working week of the «Ley» policy. */
export type LeaveSettingData = {
    trackingSince: string;
    statutoryWorkWeek: LeaveWorkWeek;
};

/** What the check of a file of opening balances found: the first rows of each list, and how many there are in all. */
export type LeaveImportSummary = {
    ready: number;
    rejected: number;
    readyRows: { line: number; name: string; year: number; days: number }[];
    rejectedRows: { line: number; reason: string }[];
    /** Rejected rows beyond the ones listed. */
    hiddenRejected: number;
};
