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
    /** The QR is obligatory on the start and the end of the day. */
    requireQrOnsite: boolean;
    /** The QR is obligatory on the break marks too. */
    requireQrBreaks: boolean;
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

/** The week a person agreed (criterio 2.6): the hours of each day, the shift and where they work. */
export type AgreedWeek = {
    shiftType: 'day' | 'night' | 'mixed';
    workMode: 'onsite' | 'remote';
    minutes: WeekMinutes;
    /** The minute of the day each weekday starts, when RH set any: a day with none is null. */
    starts: StartTimes | null;
};

/** An agreed week from a day on: each day is read with the one in effect that day. */
export type WorkSchedule = AgreedWeek & {
    /** The first day it applies (Y-m-d); null for one that began with no start date: «desde siempre». */
    validFrom: string | null;
};

/** A person on the schedules page, with their schedule or null: held to 8 h a day with no rest day until RH sets one. */
export type SchedulePerson = {
    id: string;
    name: string;
    jobTitle: string;
    center: string;
    /** The one in effect today. */
    schedule: WorkSchedule | null;
    /** The ones that have not started, the soonest first. */
    upcoming: WorkSchedule[];
    /** The ones that already ended, the newest first. */
    history: WorkSchedule[];
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
    /** Today in the company's calendar (Y-m-d): where a new schedule starts unless RH picks another day. */
    today: string;
    people: Paginated<SchedulePerson>;
    canManage: boolean;
};
