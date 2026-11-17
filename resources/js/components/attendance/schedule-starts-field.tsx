import InputError from '@/components/humetria/input-error';
import TimePicker from '@/components/humetria/time-picker';
import { Label } from '@/components/ui/label';
import { WEEKDAYS, minutesOfTime } from '@/lib/attendance/schedule';
import { sharedStart } from '@/lib/attendance/schedule-starts';

/**
 * The time each day starts, optional: with one, the person's week says when they mark after the company's tolerance. Left
 * empty a day has none. Sent as minutes from midnight.
 */
export default function ScheduleStartsField({
    starts,
    onChange,
    errors,
}: {
    starts: Record<string, string>;
    onChange: (day: string, time: string) => void;
    errors: Partial<Record<string, string>>;
}) {
    return (
        <fieldset className="grid gap-3">
            <legend className="mb-1 text-sm font-medium">
                Hora de entrada de cada día (opcional)
            </legend>
            <div className="flex flex-wrap items-center gap-3">
                <Label htmlFor="schedule-start-weekdays">
                    Misma hora de lunes a viernes
                </Label>
                <TimePicker
                    id="schedule-start-weekdays"
                    className="w-32"
                    value={sharedStart(starts)}
                    onChange={(time) =>
                        WEEKDAYS.slice(0, 5).forEach(({ day }) =>
                            onChange(day, time),
                        )
                    }
                />
            </div>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                {WEEKDAYS.map(({ day, name }) => (
                    <div key={day} className="grid gap-1">
                        <Label htmlFor={`schedule-start-${day}`}>{name}</Label>
                        <TimePicker
                            id={`schedule-start-${day}`}
                            value={starts[day] ?? ''}
                            onChange={(time) => onChange(day, time)}
                            invalid={errors[`starts.${day}`] !== undefined}
                        />
                        <input
                            type="hidden"
                            name={`starts[${day}]`}
                            value={minutesOfTime(starts[day] ?? '') ?? ''}
                        />
                    </div>
                ))}
            </div>
            <p className="text-sm text-muted-foreground">
                Sin hora de entrada, el día no tiene retardo. Solo se muestra:
                no descuenta tiempo.
            </p>
            <InputError
                message={
                    errors['starts'] ??
                    WEEKDAYS.map(({ day }) => errors[`starts.${day}`]).find(
                        (error) => error !== undefined,
                    )
                }
            />
        </fieldset>
    );
}
