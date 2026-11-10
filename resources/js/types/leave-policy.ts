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
