import { WEEKDAYS, timeOfMinutes } from '@/lib/attendance/schedule';
import type { StartTimes, WeekMinutes } from '@/types';

/** The time Monday to Friday all share, to show in the «misma hora» field; empty when they differ or have none. */
export function sharedStart(starts: Record<string, string>): string {
    const [first = ''] = WEEKDAYS.slice(0, 5).map(
        ({ day }) => starts[day] ?? '',
    );

    return WEEKDAYS.slice(0, 5).every(
        ({ day }) => (starts[day] ?? '') === first,
    )
        ? first
        : '';
}

/** «Entrada 09:00», or «Entradas de 08:00 a 09:30» when the days differ; null when no working day has one. */
export function startLabel(
    minutes: WeekMinutes,
    starts: StartTimes | null,
): string | null {
    const times = WEEKDAYS.flatMap(({ day }) => {
        const start = starts?.[day];

        return (minutes[day] ?? 0) > 0 && start !== null && start !== undefined
            ? [start]
            : [];
    });

    if (times.length === 0) {
        return null;
    }

    const first = Math.min(...times);
    const last = Math.max(...times);

    return first === last
        ? `Entrada ${timeOfMinutes(first)}`
        : `Entradas de ${timeOfMinutes(first)} a ${timeOfMinutes(last)}`;
}
