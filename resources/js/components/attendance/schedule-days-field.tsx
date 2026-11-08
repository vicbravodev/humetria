import { TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/humetria/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { duration } from '@/lib/attendance/week-text';
import {
    WEEKDAYS,
    hoursText,
    minutesOf,
    overMaximum,
    weekTotal,
} from '@/lib/attendance/schedule';
import type { WeeklyMaximum, WeekMinutes } from '@/types';

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

/** The hours of each day (0 is rest), sent as minutes, with the week's total and a gentle note above the year's maximum. */
export default function ScheduleDaysField({
    initial,
    weeklyMaximum,
    errors,
}: {
    initial: WeekMinutes | null;
    weeklyMaximum: WeeklyMaximum;
    errors: Partial<Record<string, string>>;
}) {
    const [hours, setHours] = useState<Record<string, string>>(() =>
        Object.fromEntries(
            WEEKDAYS.map(({ day }) => [
                day,
                hoursText((initial ?? STARTING)[day] ?? 0),
            ]),
        ),
    );
    const minutes = Object.fromEntries(
        WEEKDAYS.map(({ day }) => [day, minutesOf(hours[day] ?? '') ?? 0]),
    );
    const total = weekTotal(minutes);
    const warning = overMaximum(total, weeklyMaximum);

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
                                setHours({
                                    ...hours,
                                    [day]: event.target.value,
                                })
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
            <p className="text-sm">
                <span className="font-medium tabular-nums">
                    {duration(total)}
                </span>{' '}
                <span className="text-muted-foreground">a la semana</span>
            </p>
            {warning !== null && (
                <p className="flex items-start gap-1.5 rounded-lg bg-warning px-3 py-2 text-sm text-pretty text-warning-foreground">
                    <TriangleAlert
                        aria-hidden
                        className="mt-0.5 size-4 shrink-0"
                    />
                    {warning}
                </p>
            )}
        </fieldset>
    );
}
