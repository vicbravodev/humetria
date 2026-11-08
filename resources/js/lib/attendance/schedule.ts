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

/** A gentle note when the week agreed passes the year's ordinary maximum: the rest counts as overtime (criterio 2.9). */
export function overMaximum(
    total: number,
    maximum: WeeklyMaximum,
): string | null {
    if (total <= maximum.minutes) {
        return null;
    }

    return `Son ${duration(total - maximum.minutes)} más que el máximo ordinario de ${duration(maximum.minutes)} a la semana en ${maximum.year}: lo que pase de ese máximo cuenta como tiempo extra.`;
}

/** The hours of a day as RH writes them («7.5», «7,5»), in minutes; null for what is not a number. */
export function minutesOf(hours: string): number | null {
    const text = hours.trim().replace(',', '.');

    if (text === '' || Number.isNaN(Number(text))) {
        return null;
    }

    return Math.round(Number(text) * 60);
}

/** Minutes as hours to edit: 450 → «7.5». */
export function hoursText(minutes: number): string {
    return String(Math.round((minutes / 60) * 100) / 100);
}
