import { TriangleAlert } from 'lucide-react';
import InputError from '@/components/humetria/input-error';
import {
    WEEKDAYS,
    overDailyLimit,
    overMaximum,
} from '@/lib/attendance/schedule';
import type { WeeklyMaximum, WeekMinutes, WorkSchedule } from '@/types';

/**
 * Gentle notes above the year's maximum, its overtime cap and the shift's daily limit; and what the server refused in a
 * day, said here too since the days may be folded.
 */
export default function ScheduleWarnings({
    minutes,
    total,
    shift,
    weeklyMaximum,
    errors,
}: {
    minutes: WeekMinutes;
    total: number;
    shift: WorkSchedule['shiftType'];
    weeklyMaximum: WeeklyMaximum;
    errors: Partial<Record<string, string>>;
}) {
    const warnings = [
        overMaximum(total, weeklyMaximum),
        overDailyLimit(minutes, shift, weeklyMaximum),
    ].filter((warning) => warning !== null);
    const field = (name: string) =>
        errors[name] ??
        WEEKDAYS.map(({ day }) => errors[`${name}.${day}`]).find(
            (error) => error !== undefined,
        );

    return (
        <>
            {warnings.map((warning) => (
                <p
                    key={warning}
                    className="flex items-start gap-1.5 rounded-lg bg-warning px-3 py-2 text-sm text-pretty text-warning-foreground"
                >
                    <TriangleAlert
                        aria-hidden
                        className="mt-0.5 size-4 shrink-0"
                    />
                    {warning}
                </p>
            ))}
            <InputError message={field('minutes')} />
            <InputError message={field('starts')} />
            <InputError message={errors['work_mode']} />
            <InputError message={errors['employee_ids']} />
        </>
    );
}
