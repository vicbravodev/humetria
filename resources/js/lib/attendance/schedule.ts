import type { RibbonDay } from '@/lib/attendance/week-ribbon';
import { duration } from '@/lib/attendance/week-text';
import type { WeeklyMaximum, WeekMinutes, WorkSchedule } from '@/types';

/** The ISO weekdays, Monday first, with their letter (X for miércoles, as on a calendar). */
export const WEEKDAYS = [
    { day: '1', letter: 'L', name: 'Lunes' },
    { day: '2', letter: 'M', name: 'Martes' },
    { day: '3', letter: 'X', name: 'Miércoles' },
    { day: '4', letter: 'J', name: 'Jueves' },
    { day: '5', letter: 'V', name: 'Viernes' },
    { day: '6', letter: 'S', name: 'Sábado' },
    { day: '7', letter: 'D', name: 'Domingo' },
] as const;

type Run = { from: string; to: string; minutes: number };

/** «L–V 8 h · S 4 h»: the days in a row with the same hours, rest days left out. */
export function scheduleDays(minutes: WeekMinutes): string {
    const runs: Run[] = [];

    for (const { day, letter } of WEEKDAYS) {
        const today = minutes[day] ?? 0;
        const last = runs.at(-1);

        if (today === 0) {
            runs.push({ from: '', to: '', minutes: 0 });
        } else if (last !== undefined && last.minutes === today) {
            last.to = letter;
        } else {
            runs.push({ from: letter, to: letter, minutes: today });
        }
    }

    const worked = runs.filter((run) => run.minutes > 0);

    if (worked.length === 0) {
        return 'Sin días de trabajo';
    }

    return worked
        .map(
            (run) =>
                `${run.from === run.to ? run.from : `${run.from}–${run.to}`} ${duration(run.minutes)}`,
        )
        .join(' · ');
}

/** All the minutes of the week. */
export function weekTotal(minutes: WeekMinutes): number {
    return WEEKDAYS.reduce((total, { day }) => total + (minutes[day] ?? 0), 0);
}

const SHIFTS: Record<WorkSchedule['shiftType'], string> = {
    day: 'Diurna',
    night: 'Nocturna',
    mixed: 'Mixta',
};

const MODES: Record<WorkSchedule['workMode'], string> = {
    onsite: 'Presencial',
    remote: 'Remoto',
};

export function shiftLabel(shift: WorkSchedule['shiftType']): string {
    return SHIFTS[shift];
}

export function modeLabel(mode: WorkSchedule['workMode']): string {
    return MODES[mode];
}

/**
 * A gentle note when the week agreed passes the year's ordinary maximum: the rest counts as overtime (criterio 2.9); and
 * when that rest passes the year's weekly overtime cap too.
 */
export function overMaximum(
    total: number,
    maximum: WeeklyMaximum,
): string | null {
    const extra = total - maximum.minutes;

    if (extra <= 0) {
        return null;
    }

    const note = `Son ${duration(extra)} más que el máximo ordinario de ${duration(maximum.minutes)} a la semana en ${maximum.year}: lo que pase de ese máximo cuenta como tiempo extra.`;

    return extra > maximum.overtimeMinutes
        ? `${note} Además, pasa de las ${duration(maximum.overtimeMinutes)} de tiempo extra que la ley permite a la semana.`
        : note;
}

const SHIFT_NAMES: Record<WorkSchedule['shiftType'], string> = {
    day: 'diurna',
    night: 'nocturna',
    mixed: 'mixta',
};

/**
 * A gentle note naming the days of a night or mixed schedule above that shift's daily ordinary limit (art. 61). A day
 * shift's longer days count by the week (criterio 2.9), so they get no note.
 */
export function overDailyLimit(
    minutes: WeekMinutes,
    shift: WorkSchedule['shiftType'],
    maximum: WeeklyMaximum,
): string | null {
    const limit = maximum.dailyMinutes[shift];
    const days = WEEKDAYS.filter(({ day }) => (minutes[day] ?? 0) > limit).map(
        ({ name }) => `el ${name.toLowerCase()}`,
    );

    if (shift === 'day' || days.length === 0) {
        return null;
    }

    const list =
        days.length === 1
            ? days.join('')
            : `${days.slice(0, -1).join(', ')} y ${days.at(-1) ?? ''}`;

    return `La jornada ${SHIFT_NAMES[shift]} es de hasta ${duration(limit)} al día: ${list} ${days.length === 1 ? 'tiene' : 'tienen'} más.`;
}

/** The hours of a day as RH writes them («7.5», «7,5», «.5»), in minutes; null for anything but digits and one point. */
export function minutesOf(hours: string): number | null {
    const text = hours.trim().replace(',', '.');

    if (!/^(\d+\.?\d*|\.\d+)$/.test(text)) {
        return null;
    }

    return Math.round(Number(text) * 60);
}

/** Minutes as hours to edit: 450 → «7.5». */
export function hoursText(minutes: number): string {
    return String(Math.round((minutes / 60) * 100) / 100);
}

/** The time of a field («09:00») as minutes from midnight; none for an empty or odd one. */
export function minutesOfTime(time: string): number | null {
    const match = /^(\d{2}):(\d{2})$/.exec(time);
    const hours = Number(match?.[1]);
    const minutes = Number(match?.[2]);

    return match === null || hours > 23 || minutes > 59
        ? null
        : hours * 60 + minutes;
}

/** Minutes from midnight as the time a field takes: 540 → «09:00»; empty for none. */
export function timeOfMinutes(minutes: number | null | undefined): string {
    if (minutes === null || minutes === undefined) {
        return '';
    }

    const pad = (value: number) => String(value).padStart(2, '0');

    return `${pad(Math.floor(minutes / 60))}:${pad(minutes % 60)}`;
}

/** A schedule as `<WeekRibbon>` draws it: a bar per day with the minutes agreed, none of them overtime. */
export function scheduleRibbon(minutes: WeekMinutes): RibbonDay[] {
    return WEEKDAYS.map(({ day }) => ({
        worked: minutes[day] ?? 0,
        overtime: 0,
    }));
}

const SHORT_DAYS = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

/** The days off in short: «Sáb y dom de descanso». */
export function restLabel(minutes: WeekMinutes): string {
    const rest = WEEKDAYS.flatMap(({ day }, index) =>
        (minutes[day] ?? 0) === 0 ? [SHORT_DAYS[index] ?? ''] : [],
    ).map((name, index) => (index === 0 ? name : name.toLowerCase()));

    if (rest.length === 0) {
        return 'Sin día de descanso';
    }

    if (rest.length === WEEKDAYS.length) {
        return 'Todos los días de descanso';
    }

    return rest.length === 1
        ? `${rest.join('')} de descanso`
        : `${rest.slice(0, -1).join(', ')} y ${rest.at(-1) ?? ''} de descanso`;
}

export type ScheduleTemplate = {
    id: string;
    label: string;
    shift: WorkSchedule['shiftType'];
    minutes: WeekMinutes;
};

const weekOf = (daily: number, days: number): WeekMinutes =>
    Object.fromEntries(
        WEEKDAYS.map(({ day }, index) => [day, index < days ? daily : 0]),
    );

/** Where a new schedule can start from: the common weeks, the night one at the daily limit the server sends. */
export function scheduleTemplates(maximum: WeeklyMaximum): ScheduleTemplate[] {
    return [
        {
            id: 'mon_fri',
            label: 'Lunes a viernes',
            shift: 'day',
            minutes: weekOf(480, 5),
        },
        {
            id: 'mon_sat',
            label: 'Lunes a sábado',
            shift: 'day',
            minutes: weekOf(480, 6),
        },
        {
            id: 'night',
            label: 'Turno nocturno',
            shift: 'night',
            minutes: weekOf(maximum.dailyMinutes.night, 5),
        },
    ];
}

export function chosenText(count: number): string {
    return count === 1 ? '1 persona elegida' : `${count} personas elegidas`;
}

/** What setting the same schedule for all costs: those who already have one lose it. Null when nobody does. */
export function replacingText(
    chosen: readonly { schedule: unknown }[],
): string | null {
    const have = chosen.filter((person) => person.schedule !== null).length;

    if (have === 0) {
        return null;
    }

    if (chosen.length === 1) {
        return 'Ya tiene horario: se reemplaza.';
    }

    return `${have} de ellas ${have === 1 ? 'ya tiene' : 'ya tienen'} horario: se reemplaza.`;
}

/** «Ana, Bob y Carla»: the first three names, and how many more there are. */
export function peopleNames(names: readonly string[]): string {
    const shown = names.slice(0, 3);
    const more = names.length - shown.length;
    const last = more > 0 ? `${more} más` : shown.pop();

    return shown.length === 0
        ? (last ?? '')
        : `${shown.join(', ')} y ${last ?? ''}`;
}
