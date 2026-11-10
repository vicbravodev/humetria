import { TriangleAlert } from 'lucide-react';
import InputError from '@/components/humetria/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    WEEKDAYS,
    minutesOf,
    overDailyLimit,
    overMaximum,
} from '@/lib/attendance/schedule';
import type { WeeklyMaximum, WeekMinutes, WorkSchedule } from '@/types';

/**
 * The hours of each day as typed (0 is rest), sent as minutes, with gentle notes above the year's maximum, its overtime cap
 * and the shift's daily limit.
 */
export default function ScheduleDaysField({
    hours,
    onChange,
    minutes,
    total,
    shift,
    weeklyMaximum,
    errors,
}: {
    hours: Record<string, string>;
    onChange: (day: string, text: string) => void;
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

    return (
        <fieldset className="grid gap-3">
            <legend className="mb-1 text-sm font-medium">
                Horas de cada día (0 es descanso)
            </legend>
            <div className="grid grid-cols-4 gap-3 sm:grid-cols-7">
                {WEEKDAYS.map(({ day, letter, name }) => (
                    <div key={day} className="grid gap-1">
                        <Label htmlFor={`schedule-day-${day}`} title={name}>
                            <span aria-hidden>{letter}</span>
                            <span className="sr-only">{name}</span>
                        </Label>
                        <Input
                            id={`schedule-day-${day}`}
                            inputMode="decimal"
                            value={hours[day] ?? ''}
                            onChange={(event) =>
                                onChange(day, event.target.value)
                            }
                            aria-invalid={
                                errors[`minutes.${day}`] === undefined
                                    ? undefined
                                    : true
                            }
                        />
                        <input
                            type="hidden"
                            name={`minutes[${day}]`}
                            value={minutesOf(hours[day] ?? '') ?? ''}
                        />
                    </div>
                ))}
            </div>
            <InputError
                message={
                    errors['minutes'] ??
                    WEEKDAYS.map(({ day }) => errors[`minutes.${day}`]).find(
                        (error) => error !== undefined,
                    )
                }
            />
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
        </fieldset>
    );
}
