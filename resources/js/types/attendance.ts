import type { Paginated } from './demos';

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
    /** Without an agreed schedule they are held to 8 h a day with no rest day, until RH sets one. */
    hasSchedule: boolean;
    /** Minutes worked each day, Monday to Sunday, and of them the overtime: what the week ribbon draws. */
    days: { worked: number; overtime: number }[];
    /** The days of the week they were away with vacations or a leave (0 Monday to 6 Sunday): not days they missed. */
    absentDays: number[];
};

/** A center in use with its active people, its zones and the screens that show its code. */
export type OverviewCenter = {
    id: string;
    name: string;
    people: number;
    zones: number;
    screens: number;
};

/** What a center still needs before its people can mark in it. */
export type SetupCenterNeed = 'zone' | 'screen';

/** How far the company is from the first mark (SetupProgress): counts and a few names, never a position or a device. */
export type AttendanceSetup = {
    /** The day the registro is mandatory (LFT art. 132-XXXIV), Y-m-d, and the days left to it. */
    deadline: string;
    daysLeft: number;
    /** Whether the policy asks for the QR on site, so a center needs a screen that shows the code. */
    requiresQr: boolean;
    centers: {
        done: number;
        total: number;
        /** Up to five centers that cannot take a mark yet, with what each lacks. */
        pending: { id: string; name: string; needs: SetupCenterNeed[] }[];
    };
    schedules: { done: number; total: number; pending: string[] };
    devices: { done: number; total: number };
    policy: { done: number; total: number };
    /** Zones smaller than the accuracy the policy accepts, one per center, five at most. */
    findings: {
        center: { id: string; name: string };
        zone: string;
        radius: number;
        tolerance: number;
    }[];
};

export type AttendanceSetupProps = {
    setup: AttendanceSetup;
    canManage: boolean;
};

export type AttendanceOverviewProps = {
    week: OverviewWeek;
    center: { id: string; name: string } | null;
    filters: { q: string; status: OverviewView };
    counts: Record<OverviewView, number>;
    pendingCorrections: number;
    centersWithoutZones: number;
    setup: AttendanceSetup;
    /** The hours of everyone in scope, ordinary and overtime, in minutes. */
    hours: { ordinaryMinutes: number; overtimeMinutes: number };
    people: Paginated<OverviewPerson>;
    centers: Paginated<OverviewCenter>;
    canManage: boolean;
};

/** A zone of a center: where it is (its center) only for whoever may change it. */
export type AttendanceZone = {
    id: string;
    name: string;
    radius: number;
    latitude?: number;
    longitude?: number;
};

export type AttendanceCenterProps = {
    center: { id: string; name: string; mainActivity: string };
    /** Its address in one line, where the map of a new zone opens; null for whoever cannot add zones. */
    centerAddress: string | null;
    /** Its active people. */
    people: number;
    zones: Paginated<AttendanceZone>;
    kiosks: Paginated<KioskScreen>;
    canManage: boolean;
    /** Seconds each code of its screens lasts (config/attendance.php). */
    qrSeconds: number;
    /** Whether the policy asks for the QR on site, and how many of its screens already show the code. */
    requiresQr: boolean;
    screens: number;
    /** The meters of imprecision the policy accepts in a reading: a zone smaller than this is a risk. */
    tolerance: number;
};

/** A QR screen of a center: issued until it shows the code, active once seen, revoked. Local times; never its token. */
export type KioskScreen = {
    id: string;
    state: 'issued' | 'active' | 'revoked';
    issuedAt: string;
    lastSeenAt: string | null;
    revokedAt: string | null;
};

/** One day of a person in the report for the authority: local dates and times, minutes as criterio 2.9 splits them. */
export type ReportDay = {
    date: string;
    start: string | null;
    end: string | null;
    endDate: string | null;
    breakMinutes: number;
    workedMinutes: number;
    ordinaryMinutes: number;
    doubleMinutes: number;
    tripleMinutes: number;
    restDayMinutes: number;
    /** Fuerte, Media, Básica or Corregida: the weakest of the day's marks. */
    evidence: string;
    corrections: string[];
    confirmation: 'confirmado' | 'objetada' | 'sin confirmar';
    flags: string[];
    /** Minutes late against the schedule's start time and the tolerance; null when there is none to compare. Informational. */
    lateMinutes?: number | null;
};

/** A person with days in the report's period, each of them. */
export type ReportPerson = {
    id: string;
    name: string;
    jobTitle: string;
    days: ReportDay[];
};

/** The report's figures over every person-day of the period. */
export type ReportSummary = {
    people: number;
    days: number;
    workedMinutes: number;
    confirmedPercent: number;
    withCorrections: number;
    unconfirmed: number;
    /** Days whose week the person confirmed. */
    confirmedDays: number;
    ordinaryMinutes: number;
    /** The double and triple minutes: what is paid beyond the ordinary. */
    overtimeMinutes: number;
    overTwelveDays: number;
    withoutEndDays: number;
    /** People with at least one flagged day / at least one day of a week no one answered. */
    flaggedPeople: number;
    unconfirmedPeople: number;
};

/** Which people the report lists: all, those with a flagged day or those with a week no one answered. */
export type ReportShow = 'all' | 'flagged' | 'unconfirmed';

/** A usual period the report offers: its first and last local day. */
export type ReportPreset = {
    key: 'this-week' | 'last-week' | 'last-month';
    label: string;
    from: string;
    to: string;
};

export type AttendanceReportProps = {
    center: { id: string; name: string } | null;
    period: { from: string; to: string };
    presets: ReportPreset[];
    /** How many days the screen shows and how many the CSV takes. */
    limits: { screenDays: number; exportDays: number };
    filters: { q: string; show: ReportShow };
    summary: ReportSummary | null;
    people: Paginated<ReportPerson> | null;
    /** When the report was put together, as «Y-m-d H:i» in UTC and in the display time zone. */
    generatedAt: { utc: string; local: string } | null;
    exportUrl: string | null;
    /** A period too long for the screen: how many days it has and how many the screen shows; only its CSV is offered. */
    longPeriod: { days: number; maxOnScreen: number } | null;
};
