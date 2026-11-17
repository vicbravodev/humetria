import type {
    AttendanceCenterProps,
    AttendanceOverviewProps,
    OverviewCenter,
} from './attendance';
import type { Paginated } from './demos';

/** How the people of a center mark: as the company's policy says, or in the field, from their phone wherever they are. */
export type CenterMarkMode = 'company' | 'field';

/** A center of the overview with how its people mark. */
export type ModedCenter = OverviewCenter & { mode: CenterMarkMode };

/** RH's overview, its centers with how their people mark. */
export type AttendanceOverviewPageProps = Omit<
    AttendanceOverviewProps,
    'centers'
> & { centers: Paginated<ModedCenter> };

/** A center of the registro with how its people mark. */
export type AttendanceCenterPageProps = AttendanceCenterProps & {
    mode: CenterMarkMode;
};

/** The company's policy of the registro, as saved or the defaults it starts with. */
export type AttendancePolicy = {
    outOfZone: 'block' | 'allow_and_flag';
    maxAccuracyM: number;
    requireQrOnsite: boolean;
    trustedMayUseBrowser: boolean;
    retentionMonths: number;
    /** Minutes after a start time that still count as on time: the lateness is shown, never sanctioned. */
    lateToleranceMinutes: number;
};

export type AttendancePolicyProps = {
    policy: AttendancePolicy;
    /** False while RH has not saved one: the page shows the defaults. */
    saved: boolean;
    canManage: boolean;
    /** The centers in use that mark in the field instead of by this policy. */
    fieldCenters: number;
};

/** The tabs of the schedules page: everyone, with an agreed schedule, and without one. */
export type ScheduleView = 'all' | 'scheduled' | 'unscheduled';

/** Minutes agreed by ISO weekday, «1» Monday to «7» Sunday; 0 is a rest day. */
export type WeekMinutes = Partial<Record<string, number>>;

/** The start time of each weekday as minutes from midnight; a day with none is null. */
export type StartTimes = Partial<Record<string, number | null>>;

/** A person's agreed weekly schedule (criterio 2.6). */
export type WorkSchedule = {
    shiftType: 'day' | 'night' | 'mixed';
    workMode: 'onsite' | 'remote';
    minutes: WeekMinutes;
    /** The minute of the day each weekday starts, when RH set any: a day with none is null. */
    starts: StartTimes | null;
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
