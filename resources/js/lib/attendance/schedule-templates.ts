import { WEEKDAYS, scheduleDays, weekTotal } from '@/lib/attendance/schedule';
import { duration } from '@/lib/attendance/week-text';
import type { WeeklyMaximum, WeekMinutes, WorkSchedule } from '@/types';

export type ScheduleTemplate = {
    id: string;
    title: string;
    shift: WorkSchedule['shiftType'];
    minutes: WeekMinutes;
};

/** Down to the quarter hour. */
const quarter = (minutes: number) => Math.max(0, Math.floor(minutes / 15) * 15);

/** The first `days` weekdays at `daily`, Saturday at `saturday`, the rest at 0. */
const weekOf = (daily: number, days: number, saturday = 0): WeekMinutes =>
    Object.fromEntries(
        WEEKDAYS.map(({ day }, index) => [
            day,
            index < days ? daily : index === 5 ? saturday : 0,
        ]),
    );

/**
 * Where a schedule starts from: the common weeks, each with its shift; none fills a start time (RH writes it on purpose). Their days shrink to the
 * quarter hour that fits the year's weekly maximum, so none starts above it (in 2027, Monday to Saturday is 7 h 30 min);
 * the night one is at the night shift's daily limit the server sends.
 */
export function scheduleTemplates(maximum: WeeklyMaximum): ScheduleTemplate[] {
    const fit = (daily: number, days: number) =>
        Math.min(daily, quarter(maximum.minutes / days));
    const weekday = fit(480, 5);

    return [
        {
            id: 'mon_fri',
            title: 'Lunes a viernes',
            shift: 'day',
            minutes: weekOf(weekday, 5),
        },
        {
            id: 'mon_fri_9',
            title: 'Lunes a viernes, 9 h',
            shift: 'day',
            minutes: weekOf(fit(540, 5), 5),
        },
        {
            id: 'mon_fri_half_sat',
            title: 'Con sábado medio',
            shift: 'day',
            minutes: weekOf(
                weekday,
                5,
                Math.min(300, quarter(maximum.minutes - weekday * 5)),
            ),
        },
        {
            id: 'mon_sat',
            title: 'Lunes a sábado',
            shift: 'day',
            minutes: weekOf(fit(480, 6), 6),
        },
        {
            id: 'night',
            title: 'Nocturno',
            shift: 'night',
            minutes: weekOf(fit(maximum.dailyMinutes.night, 5), 5),
        },
    ];
}

/** What a template fills, with its total: «L–V 8 h · S 5 h · 45 h». */
export function templateSummary(template: ScheduleTemplate): string {
    return `${scheduleDays(template.minutes)} · ${duration(weekTotal(template.minutes))}`;
}

/** The template whose days have exactly these minutes; null when none. */
export function matchTemplate(
    templates: readonly ScheduleTemplate[],
    minutes: WeekMinutes,
): string | null {
    return (
        templates.find((template) =>
            WEEKDAYS.every(
                ({ day }) =>
                    (template.minutes[day] ?? 0) === (minutes[day] ?? 0),
            ),
        )?.id ?? null
    );
}
