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
    reason: string;
    /** Days the request waits since it was asked. */
    ageDays: number;
    status: CorrectionStatus;
    note: string | null;
};

export type CorrectionCounts = Record<CorrectionStatus, number>;

/** The tabs of RH's overview: every person, each answer to the week, and the weeks near the year's limits. */
export type OverviewView =
    | 'all'
    | 'pending'
    | 'disputed'
    | 'confirmed'
    | 'at_risk';

/** The week the overview shows: its Monday and Sunday, whether it ended, and the weeks around it (null: still to come). */
export type OverviewWeek = {
    start: string;
    end: string;
    over: boolean;
    previous: string;
    next: string | null;
};

/** A person's week in RH's overview: their hours, the limits broken and their answer. Never a position or a device. */
export type OverviewPerson = {
    id: string;
    name: string;
    jobTitle: string;
    center: string;
    ordinaryMinutes: number;
    overtimeMinutes: number;
    flags: string[];
    status: 'pending' | 'confirmed' | 'disputed';
    atRisk: boolean;
};

/** A center in use with its active people, its zones and the screens that show its code. */
export type OverviewCenter = {
    id: string;
    name: string;
    people: number;
    zones: number;
    screens: number;
};

export type AttendanceOverviewProps = {
    week: OverviewWeek;
    center: { id: string; name: string } | null;
    filters: { q: string; status: OverviewView };
    counts: Record<OverviewView, number>;
    pendingCorrections: number;
    centersWithoutZones: number;
    people: Paginated<OverviewPerson>;
    centers: Paginated<OverviewCenter>;
    canManage: boolean;
};
