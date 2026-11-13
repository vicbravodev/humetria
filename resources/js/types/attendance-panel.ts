import type { Paginated } from './demos';

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
    /** The link waiting for them: sent (and the hours it has left) or expired; null when none waits. */
    link: {
        state: 'sent' | 'expired';
        sentAt: string;
        hoursLeft: number | null;
    } | null;
    devices: LinkedDevice[];
};

/** How many people are at a stage of the road to the phone, and the names of the first three. */
export type DeviceStage = { count: number; names: string[] };

export type AttendanceDevicesProps = {
    /** The center asked for, with what it has to mark with: zones, screens in use and whether the policy asks for the QR. */
    center: {
        id: string;
        name: string;
        zones: number;
        screens: number;
        requiresQr: boolean;
    } | null;
    filters: { q: string; status: DeviceView };
    counts: Record<DeviceView, number>;
    /** Everyone in scope on the road to the phone: no open link, a link sent, with a phone. */
    pipeline: Record<'none' | 'sent' | 'linked', DeviceStage>;
    people: Paginated<DevicePerson>;
    canManage: boolean;
    /** Hours an enrollment link lasts (config/attendance.php). */
    linkHours: number;
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

/**
 * The year's weekly ordinary maximum (art. 59 and transitorio segundo), its weekly overtime cap (transitorio cuarto) and
 * each shift's daily ordinary limit (art. 61), in minutes: the server's LegalLimits, the one place these numbers live.
 */
export type WeeklyMaximum = {
    year: number;
    minutes: number;
    overtimeMinutes: number;
    dailyMinutes: Record<WorkSchedule['shiftType'], number>;
};

export type AttendanceSchedulesProps = {
    center: { id: string; name: string } | null;
    filters: { q: string; status: ScheduleView };
    counts: Record<ScheduleView, number>;
    weeklyMaximum: WeeklyMaximum;
    people: Paginated<SchedulePerson>;
    canManage: boolean;
};
