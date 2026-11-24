import { useState } from 'react';
import { WEEKDAYS, minutesOf, weekTotal } from '@/lib/attendance/schedule';
import {
    deducedShift,
    draftOf,
    sharedStart,
    startsDiffer,
    withHours,
    withRest,
    withStart,
    withWeek,
} from '@/lib/attendance/schedule-draft';
import type { DraftWeek } from '@/lib/attendance/schedule-draft';
import type { ScheduleTemplate } from '@/lib/attendance/schedule-templates';
import { matchTemplate } from '@/lib/attendance/schedule-templates';
import type { AgreedWeek, WeekMinutes } from '@/types';

type Shift = AgreedWeek['shiftType'];

/** Monday to Friday 8 h with no start time: where a new schedule starts. */
const STARTING: WeekMinutes = { 1: 480, 2: 480, 3: 480, 4: 480, 5: 480 };

/**
 * The schedule RH is writing: the week (hours as typed and start of each day), where they work and the shift. The shift is
 * the one the week's hours say (art. 60), the template's or the saved one while no day has a start, or the one RH changed
 * it to. A template fills the days and hours and keeps the start RH had; another person's schedule fills all of the week.
 */
export function useScheduleDraft(
    initial: AgreedWeek | null,
    templates: readonly ScheduleTemplate[],
) {
    const [week, setWeek] = useState<DraftWeek>(() =>
        draftOf(initial?.minutes ?? STARTING, initial?.starts ?? null),
    );
    const [mode, setMode] = useState(initial?.workMode ?? 'onsite');
    const [base, setBase] = useState<Shift>(initial?.shiftType ?? 'day');
    const [chosenShift, setChosenShift] = useState<Shift | null>(null);
    // A saved week no template gives, or with days that start at different times, opens «Personalizar días».
    const [customized] = useState(
        () =>
            initial !== null &&
            (matchTemplate(templates, initial.minutes) === null ||
                startsDiffer(week)),
    );
    const minutes = Object.fromEntries(
        WEEKDAYS.map(({ day }) => [day, minutesOf(week.hours[day] ?? '') ?? 0]),
    );
    const fill = (next: DraftWeek, shift: Shift) => {
        setWeek(next);
        setBase(shift);
        setChosenShift(null);
    };

    return {
        week,
        customized,
        startsDiffer: startsDiffer(week),
        minutes,
        total: weekTotal(minutes),
        template: matchTemplate(templates, minutes),
        start: sharedStart(week),
        shift: chosenShift ?? deducedShift(week) ?? base,
        mode,
        setMode,
        setShift: setChosenShift,
        setHours: (day: string, text: string) =>
            setWeek((current) => withHours(current, day, text)),
        setRest: (day: string, rest: boolean) =>
            setWeek((current) => withRest(current, day, rest)),
        setStart: (time: string) =>
            setWeek((current) => withStart(current, time)),
        setDayStart: (day: string, time: string) =>
            setWeek((current) => ({
                ...current,
                starts: { ...current.starts, [day]: time },
            })),
        apply: (template: ScheduleTemplate) =>
            fill(withWeek(week, template.minutes), template.shift),
        copy: (schedule: AgreedWeek) =>
            fill(
                draftOf(schedule.minutes, schedule.starts),
                schedule.shiftType,
            ),
    };
}

export type ScheduleDraft = ReturnType<typeof useScheduleDraft>;
