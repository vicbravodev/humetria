import TimePicker from '@/components/humetria/time-picker';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

/**
 * A day of the week: its hours and the time it starts, or «Descansa», dimmed and with nothing to fill. A day with hours
 * that cannot be read stays a working day, for the server to say why.
 */
export default function ScheduleDayRow({
    day,
    name,
    hours,
    start,
    rest,
    invalid,
    onHours,
    onStart,
    onRest,
}: {
    day: string;
    name: string;
    hours: string;
    start: string;
    rest: boolean;
    invalid: { hours: boolean; start: boolean };
    onHours: (text: string) => void;
    onStart: (time: string) => void;
    onRest: (rest: boolean) => void;
}) {
    const lower = name.toLowerCase();

    return (
        <div
            data-rest={rest || undefined}
            className="grid grid-cols-[minmax(0,1fr)_4rem_7.5rem] items-center gap-x-2 border-t py-2 first:border-t-0 sm:grid-cols-[minmax(0,1fr)_4.5rem_7.5rem] sm:gap-x-3"
        >
            <div className="grid gap-1">
                <span
                    className={cn(
                        'font-medium',
                        rest && 'text-muted-foreground',
                    )}
                >
                    {name}
                </span>
                <div className="flex items-center gap-2">
                    <Checkbox
                        id={`schedule-rest-${day}`}
                        checked={rest}
                        onCheckedChange={(next) => onRest(next === true)}
                    />
                    <Label
                        htmlFor={`schedule-rest-${day}`}
                        className="text-sm font-normal text-muted-foreground"
                    >
                        Descansa
                        <span className="sr-only"> el {lower}</span>
                    </Label>
                </div>
            </div>
            {rest ? (
                <span className="col-span-2 text-sm text-muted-foreground">
                    Día de descanso
                </span>
            ) : (
                <>
                    <Input
                        id={`schedule-day-${day}`}
                        aria-label={`Horas del ${lower}`}
                        inputMode="decimal"
                        value={hours}
                        onChange={(event) => onHours(event.target.value)}
                        aria-invalid={invalid.hours || undefined}
                    />
                    <div>
                        <Label
                            htmlFor={`schedule-start-${day}`}
                            className="sr-only"
                        >
                            Entrada del {lower}
                        </Label>
                        <TimePicker
                            id={`schedule-start-${day}`}
                            value={start}
                            onChange={onStart}
                            invalid={invalid.start}
                        />
                    </div>
                </>
            )}
        </div>
    );
}
