import { duration } from '@/lib/attendance/week-text';

/**
 * The «cinta de la semana»: seven bars, Monday to Sunday, one per day, whose top is 12 h (the legal day maximum). Feed
 * `ribbonBars` the minutes of each day and render them with `<WeekRibbon>` (components/humetria/week-ribbon.tsx).
 */

/** What a bar's top stands for, in minutes. */
export const RIBBON_CAP_MINUTES = 12 * 60;

/** One day: all the minutes worked and, of them, the overtime. */
export type RibbonDay = { worked: number; overtime: number };

/** One bar: its ordinary and overtime parts as a percentage of the cap, and whether the day passed 12 h. */
export type RibbonBar = {
    empty: boolean;
    ordinary: number;
    overtime: number;
    over: boolean;
};

const DAYS = [
    'lunes',
    'martes',
    'miércoles',
    'jueves',
    'viernes',
    'sábado',
    'domingo',
];

/** The initial of each day, Monday first: what goes under a bar when the ribbon is read at a glance. */
export const RIBBON_INITIALS = ['L', 'M', 'M', 'J', 'V', 'S', 'D'] as const;

const percent = (minutes: number): number =>
    Math.min(100, (minutes / RIBBON_CAP_MINUTES) * 100);

/** Seven bars for the days given (missing ones are empty days). */
export function ribbonBars(days: readonly RibbonDay[]): RibbonBar[] {
    return DAYS.map((_, index) => {
        const { worked, overtime } = days[index] ?? { worked: 0, overtime: 0 };
        const ordinary = Math.min(
            RIBBON_CAP_MINUTES,
            Math.max(0, worked - overtime),
        );

        return {
            empty: worked === 0,
            ordinary: percent(ordinary),
            overtime: Math.min(percent(overtime), 100 - percent(ordinary)),
            over: worked > RIBBON_CAP_MINUTES,
        };
    });
}

/** What a screen reader says of the ribbon: «Horas por día: lunes 8 h, martes sin horas, …». */
export function ribbonLabel(days: readonly RibbonDay[]): string {
    const each = DAYS.map((name, index) => {
        const worked = days[index]?.worked ?? 0;

        return `${name} ${worked === 0 ? 'sin horas' : duration(worked)}`;
    });

    return `Horas por día: ${each.join(', ')}`;
}
