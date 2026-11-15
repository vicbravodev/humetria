import { duration } from '@/lib/attendance/week-text';

/** «Retardo de 11 min»; nothing when the day has no lateness (or no start time to compare with): it is shown, never sanctioned. */
export function lateLabel(minutes: number | null | undefined): string | null {
    return minutes === null || minutes === undefined || minutes === 0
        ? null
        : `Retardo de ${duration(minutes)}`;
}

/** «2 retardos» over the days given; nothing when none was late. */
export function lateCount(
    days: { lateMinutes?: number | null }[],
): string | null {
    const late = days.filter((day) => (day.lateMinutes ?? 0) > 0).length;

    if (late === 0) {
        return null;
    }

    return late === 1 ? '1 retardo' : `${late} retardos`;
}
