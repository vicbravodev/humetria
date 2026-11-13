import { useState } from 'react';
import {
    WEEKDAYS,
    hoursText,
    minutesOf,
    timeOfMinutes,
    weekTotal,
} from '@/lib/attendance/schedule';
import type { ScheduleTemplate } from '@/lib/attendance/schedule';
import type { WeekMinutes, WorkSchedule } from '@/types';

/** Monday to Friday 8 h: where a new schedule starts. */
const STARTING: WeekMinutes = {
    1: 480,
    2: 480,
    3: 480,
    4: 480,
    5: 480,
    6: 0,
    7: 0,
};

/**
 * The schedule RH is writing: the shift, where they work and the hours of each day as typed (so «7,5» stays «7,5» until it is
 * read), with their minutes and the week's total; a template fills all of it. The start time of each day, if RH sets one,
 * is kept as the time a field takes, apart from the template: it is the person's, not the shift's.
 */
export function useScheduleDraft(initial: WorkSchedule | null) {
    const [shift, setShift] = useState(initial?.shiftType ?? 'day');
    const [mode, setMode] = useState(initial?.workMode ?? 'onsite');
    const [hours, setHours] = useState<Record<string, string>>(() =>
        Object.fromEntries(
            WEEKDAYS.map(({ day }) => [
                day,
                hoursText((initial?.minutes ?? STARTING)[day] ?? 0),
            ]),
        ),
    );
    const [starts, setStarts] = useState<Record<string, string>>(() =>
        Object.fromEntries(
            WEEKDAYS.map(({ day }) => [
                day,
                timeOfMinutes(initial?.starts?.[day]),
            ]),
        ),
    );
    const minutes = Object.fromEntries(
        WEEKDAYS.map(({ day }) => [day, minutesOf(hours[day] ?? '') ?? 0]),
    );

    return {
        shift,
        setShift,
        mode,
        setMode,
        hours,
        setDay: (day: string, text: string) =>
            setHours((current) => ({ ...current, [day]: text })),
        starts,
        setStart: (day: string, time: string) =>
            setStarts((current) => ({ ...current, [day]: time })),
        minutes,
        total: weekTotal(minutes),
        apply: (template: ScheduleTemplate) => {
            setShift(template.shift);
            setHours(
                Object.fromEntries(
                    WEEKDAYS.map(({ day }) => [
                        day,
                        hoursText(template.minutes[day] ?? 0),
                    ]),
                ),
            );
        },
    };
}

export type ScheduleDraft = ReturnType<typeof useScheduleDraft>;
