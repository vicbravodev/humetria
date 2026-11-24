import { shortDate } from '@/lib/calendar-date';
import type { WorkSchedule } from '@/types';

/** One line of a person's schedule over time: what applies now, what is coming, or what applied before. */
export type ValidityEntry = {
    kind: 'coming' | 'current' | 'past';
    since: string;
    schedule: WorkSchedule;
};

/** «Desde el 1 dic 2026», or «Desde siempre» for the schedule that began with no start date. */
export function sinceText(validFrom: string | null): string {
    return validFrom === null
        ? 'Desde siempre'
        : `Desde el ${shortDate(validFrom)}`;
}

/**
 * The next change, from the soonest upcoming schedule: «Cambia el 1 dic 2026» when it replaces one in effect, «Empieza el
 * …» when the person has none yet; nothing when none is coming.
 */
export function comingNote(
    upcoming: readonly WorkSchedule[],
    replacing: boolean,
): string | null {
    const [next] = upcoming;

    return next?.validFrom === undefined || next.validFrom === null
        ? null
        : `${replacing ? 'Cambia' : 'Empieza'} el ${shortDate(next.validFrom)}`;
}

/**
 * The person's schedules from the farthest ahead to the oldest: what is coming (the server sends the soonest first, so
 * they are turned around), what applies today and the past, newest first as the server sends it.
 */
export function validityTimeline(
    current: WorkSchedule | null,
    upcoming: readonly WorkSchedule[],
    history: readonly WorkSchedule[],
): ValidityEntry[] {
    const entry =
        (kind: ValidityEntry['kind']) => (schedule: WorkSchedule) => ({
            kind,
            since: sinceText(schedule.validFrom),
            schedule,
        });

    return [
        ...[...upcoming].reverse().map(entry('coming')),
        ...(current === null ? [] : [entry('current')(current)]),
        ...history.map(entry('past')),
    ];
}
