import {
    WEEKDAYS,
    hoursText,
    minutesOf,
    minutesOfTime,
    timeOfMinutes,
} from '@/lib/attendance/schedule';
import type { StartTimes, WeekMinutes, WorkSchedule } from '@/types';

/** A week as RH writes it: the hours of each day as typed («7,5» stays «7,5») and its start as a field takes it. */
export type DraftWeek = {
    hours: Record<string, string>;
    starts: Record<string, string>;
};

const byDay = (value: (day: string) => string): Record<string, string> =>
    Object.fromEntries(WEEKDAYS.map(({ day }) => [day, value(day)]));

/** A day of 0 hours; one whose hours are being typed (empty) still works. */
export function isRest(week: DraftWeek, day: string): boolean {
    return minutesOf(week.hours[day] ?? '') === 0;
}

const working = (week: DraftWeek) =>
    WEEKDAYS.map(({ day }) => day).filter((day) => !isRest(week, day));

export function draftOf(
    minutes: WeekMinutes,
    starts: StartTimes | null,
): DraftWeek {
    return {
        hours: byDay((day) => hoursText(minutes[day] ?? 0)),
        starts: byDay((day) => timeOfMinutes(starts?.[day])),
    };
}

export function withHours(
    week: DraftWeek,
    day: string,
    text: string,
): DraftWeek {
    return { ...week, hours: { ...week.hours, [day]: text } };
}

/** The time every working day shares; empty when they differ or none has one. */
export function sharedStart(week: DraftWeek): string {
    const times = new Set(working(week).map((day) => week.starts[day] ?? ''));
    const [only = ''] = times;

    return times.size === 1 ? only : '';
}

/** The working days start at different times (one with none counts as different from one with a start). */
export function startsDiffer(week: DraftWeek): boolean {
    return new Set(working(week).map((day) => week.starts[day] ?? '')).size > 1;
}

/**
 * The hours of a template on the week, keeping the start RH had: the one shared goes to every working day, or each
 * day's when they differ; with none, none (RH writes it on purpose).
 */
export function withWeek(week: DraftWeek, minutes: WeekMinutes): DraftWeek {
    const shared = sharedStart(week);
    const next = { ...draftOf(minutes, null), starts: week.starts };

    return shared === '' ? next : withStart(next, shared);
}

/** One start time for every working day; rest days keep none. */
export function withStart(week: DraftWeek, time: string): DraftWeek {
    return {
        ...week,
        starts: byDay((day) => (isRest(week, day) ? '' : time)),
    };
}

/**
 * A day goes to rest (0 h, no start) or back to work, with the hours of the first working day (8 h when none works) and
 * the start the week shares.
 */
export function withRest(
    week: DraftWeek,
    day: string,
    rest: boolean,
): DraftWeek {
    const [first] = working(week);
    const hours = rest
        ? '0'
        : first === undefined
          ? '8'
          : (week.hours[first] ?? '8');

    return {
        hours: { ...week.hours, [day]: hours },
        starts: { ...week.starts, [day]: rest ? '' : sharedStart(week) },
    };
}

/** What the form sends: the minutes of each day (empty for what cannot be read) and its start, none on a rest day. */
export function draftInputs(week: DraftWeek): {
    minutes: Record<string, string>;
    starts: Record<string, string>;
} {
    return {
        minutes: byDay((day) => String(minutesOf(week.hours[day] ?? '') ?? '')),
        starts: byDay((day) =>
            isRest(week, day)
                ? ''
                : String(minutesOfTime(week.starts[day] ?? '') ?? ''),
        ),
    };
}

type Shift = WorkSchedule['shiftType'];

const NIGHT_LEVEL: Record<Shift, number> = { day: 0, mixed: 1, night: 2 };

/** The minutes of a day's work between 20:00 and 6:00, past midnight too. */
function nightMinutes(start: number, minutes: number): number {
    let night = 0;

    for (let at = start; at < start + minutes; at++) {
        const time = at % 1440;
        night += time < 360 || time >= 1200 ? 1 : 0;
    }

    return night;
}

/**
 * The shift a week is, from when its days start and how long they last (art. 60): day between 6:00 and 20:00, night between
 * 20:00 and 6:00, mixed when a day takes both with less than 3 h 30 min of night (with more, it is night). The most nightly
 * of its days; null when no working day has a start. It only names the shift: no count changes with it (criterio 2.9).
 */
export function deducedShift(week: DraftWeek): Shift | null {
    const shifts = working(week).flatMap((day): Shift[] => {
        const start = minutesOfTime(week.starts[day] ?? '');
        const minutes = minutesOf(week.hours[day] ?? '') ?? 0;

        if (start === null) {
            return [];
        }

        const night = nightMinutes(start, minutes);

        if (night === 0) {
            return ['day'];
        }

        return [night === minutes || night >= 210 ? 'night' : 'mixed'];
    });

    return shifts.length === 0
        ? null
        : shifts.reduce((most, shift) =>
              NIGHT_LEVEL[shift] > NIGHT_LEVEL[most] ? shift : most,
          );
}
