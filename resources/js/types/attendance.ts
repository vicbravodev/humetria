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
    /** Its active people. */
    people: number;
    zones: Paginated<AttendanceZone>;
    kiosks: Paginated<KioskScreen>;
    canManage: boolean;
};

/** A QR screen of a center: issued until it shows the code, active once seen, revoked. Local times; never its token. */
export type KioskScreen = {
    id: string;
    state: 'issued' | 'active' | 'revoked';
    issuedAt: string;
    lastSeenAt: string | null;
    revokedAt: string | null;
};

/** The tabs of the devices page: everyone, with an active device, never linked, and with only revoked ones. */
export type DeviceView = 'all' | 'linked' | 'unlinked' | 'revoked';

/** A person's linked device: its name, when, and whether its key is synced. Never its credential or its key. */
export type LinkedDevice = {
    id: string;
    label: string;
    synced: boolean;
    /** Local times («Y-m-d H:i»). */
    enrolledAt: string;
    lastUsedAt: string | null;
    revokedAt: string | null;
};

/** A person on the devices page: whether they have an email (their link goes there), where they stand, their devices. */
export type DevicePerson = {
    id: string;
    name: string;
    jobTitle: string;
    center: string;
    hasEmail: boolean;
    status: Exclude<DeviceView, 'all'>;
    devices: LinkedDevice[];
};

export type AttendanceDevicesProps = {
    center: { id: string; name: string } | null;
    filters: { q: string; status: DeviceView };
    counts: Record<DeviceView, number>;
    people: Paginated<DevicePerson>;
    canManage: boolean;
};

/** The company's policy of the registro, as saved or the defaults it starts with. */
export type AttendancePolicy = {
    outOfZone: 'block' | 'allow_and_flag';
    maxAccuracyM: number;
    requireQrOnsite: boolean;
    trustedMayUseBrowser: boolean;
    retentionMonths: number;
};

export type AttendancePolicyProps = {
    policy: AttendancePolicy;
    /** False while RH has not saved one: the page shows the defaults. */
    saved: boolean;
    canManage: boolean;
};

/** The tabs of the schedules page: everyone, with an agreed schedule, and without one. */
export type ScheduleView = 'all' | 'scheduled' | 'unscheduled';

/** Minutes agreed by ISO weekday, «1» Monday to «7» Sunday; 0 is a rest day. */
export type WeekMinutes = Partial<Record<string, number>>;

/** A person's agreed weekly schedule (criterio 2.6). */
export type WorkSchedule = {
    shiftType: 'day' | 'night' | 'mixed';
    workMode: 'onsite' | 'remote';
    minutes: WeekMinutes;
};

/** A person on the schedules page, with their schedule or null: held to 8 h a day with no rest day until RH sets one. */
export type SchedulePerson = {
    id: string;
    name: string;
    jobTitle: string;
    center: string;
    schedule: WorkSchedule | null;
};

/** The year's weekly ordinary maximum (art. 59 and transitorio segundo), in minutes. */
export type WeeklyMaximum = { year: number; minutes: number };

export type AttendanceSchedulesProps = {
    center: { id: string; name: string } | null;
    filters: { q: string; status: ScheduleView };
    counts: Record<ScheduleView, number>;
    weeklyMaximum: WeeklyMaximum;
    people: Paginated<SchedulePerson>;
    canManage: boolean;
};
