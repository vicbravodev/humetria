import { Badge } from '@/components/ui/badge';
import { markName } from '@/lib/attendance/next-step';
import type { WeekDay as Day } from '@/lib/attendance/week';
import { dayTitle, duration } from '@/lib/attendance/week-text';

/** One day of the week: its marks with their local time, what it worked against what was scheduled, and its flags. */
export default function WeekDay({ day }: { day: Day }) {
    const scheduled = day.restDay
        ? 'Descanso'
        : `de ${duration(day.scheduledMinutes)}`;

    return (
        <li className="flex flex-col gap-2 py-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h3 className="text-base font-semibold">
                    {dayTitle(day.date)}
                </h3>
                <p className="text-base tabular-nums">
                    <span className="font-semibold">
                        {duration(day.workedMinutes)}
                    </span>{' '}
                    <span className="text-muted-foreground">{scheduled}</span>
                </p>
            </div>
            {day.marks.length === 0 ? (
                <p className="text-base text-muted-foreground">Sin marcas.</p>
            ) : (
                <ul className="flex flex-col gap-1">
                    {day.marks.map((mark) => (
                        <li
                            key={`${mark.type}-${mark.time}-${mark.nextDay ? 1 : 0}`}
                            className="flex flex-wrap items-center justify-between gap-2 text-base"
                        >
                            <span>
                                {markName(mark.type)}
                                {mark.corrected && (
                                    <span className="text-muted-foreground">
                                        {' '}
                                        (corregida por RH)
                                    </span>
                                )}
                            </span>
                            <span className="font-semibold tabular-nums">
                                {mark.time}
                                {mark.nextDay && (
                                    <span className="font-normal text-muted-foreground">
                                        {' '}
                                        del día siguiente
                                    </span>
                                )}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
            {day.missingEnd && (
                <Badge variant="warning">Sin salida marcada</Badge>
            )}
        </li>
    );
}
