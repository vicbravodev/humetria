import { hoursSplit } from '@/lib/attendance/overview';
import type { RibbonDay } from '@/lib/attendance/week-ribbon';
import type { ReportDay, ReportPreset } from '@/types';

const DAY_MS = 86_400_000;

/** A local date as a UTC midnight: whole days, whatever the machine's zone. */
const midnight = (date: string): number => Date.parse(`${date}T00:00:00Z`);

/** 0 for Monday to 6 for Sunday. */
const weekday = (date: string): number =>
    (new Date(midnight(date)).getUTCDay() + 6) % 7;

const overtimeOf = (day: ReportDay): number =>
    day.doubleMinutes + day.tripleMinutes;

/**
 * The «cinta de la semana» of a person's days: one bar per weekday, Monday first. Only a person whose days all fall in one
 * Monday to Sunday has one: across weeks the same bar would stand for several days.
 */
export function personRibbon(days: readonly ReportDay[]): RibbonDay[] | null {
    const first = days[0];

    if (first === undefined) {
        return null;
    }

    const weekStart = midnight(first.date) - weekday(first.date) * DAY_MS;
    const sameWeek = days.every(
        ({ date }) => midnight(date) - weekday(date) * DAY_MS === weekStart,
    );

    if (!sameWeek) {
        return null;
    }

    return Array.from({ length: 7 }, (_, index) => {
        const match = days.find(({ date }) => weekday(date) === index);

        return match === undefined
            ? { worked: 0, overtime: 0 }
            : { worked: match.workedMinutes, overtime: overtimeOf(match) };
    });
}

/** «45 h» and, apart, «5 h extra» of all the person's days; «Sin horas» when none was worked. */
export function personHours(days: readonly ReportDay[]): {
    total: string;
    extra: string | null;
} {
    const worked = days.reduce((sum, day) => sum + day.workedMinutes, 0);
    const overtime = days.reduce((sum, day) => sum + overtimeOf(day), 0);

    return hoursSplit(worked - overtime, overtime);
}

const IN_PROGRESS = 'En curso';

/** A flag RH should act on: «En curso» (today's day, not over yet) only informs. */
export const isWarning = (flag: string): boolean => flag !== IN_PROGRESS;

/** What an inspector should notice about the person: each distinct warning of their days, in the order they appear. */
export function personNotices(days: readonly ReportDay[]): string[] {
    return [...new Set(days.flatMap((day) => day.flags))].filter(isWarning);
}

/** Whether one of the person's days is still going. */
export const hasDayInProgress = (days: readonly ReportDay[]): boolean =>
    days.some((day) => day.flags.includes(IN_PROGRESS));

const WORST: ReportDay['confirmation'][] = [
    'objetada',
    'sin confirmar',
    'confirmado',
];

/** The answer that weighs most across the person's weeks: an objection, then silence (it is no conformity), then a yes. */
export function answerOf(
    days: readonly ReportDay[],
): ReportDay['confirmation'] {
    return (
        WORST.find((answer) =>
            days.some((day) => day.confirmation === answer),
        ) ?? 'sin confirmar'
    );
}

/** Which usual period the dates are, or `other`. */
export function matchPreset(
    presets: readonly ReportPreset[],
    from: string,
    to: string,
): ReportPreset['key'] | 'other' {
    return (
        presets.find((preset) => preset.from === from && preset.to === to)
            ?.key ?? 'other'
    );
}
