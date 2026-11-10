import { weekdayInitial } from '@/lib/leave/calendar';
import type { CalendarDay } from '@/lib/leave/calendar';
import { cn } from '@/lib/utils';

/** The head of the calendar: a column per day with its weekday, and under it how many are out that day. */
export default function CalendarHead({
    days,
    today,
    out,
}: {
    days: CalendarDay[];
    today: string;
    out: number[];
}) {
    return (
        <>
            <div className="flex border-b bg-muted">
                <div className="sticky left-0 z-10 flex w-44 shrink-0 items-center bg-muted px-4 text-sm font-medium text-muted-foreground">
                    Persona
                </div>
                <div
                    className="grid flex-1"
                    style={{
                        gridTemplateColumns: `repeat(${days.length}, minmax(28px, 1fr))`,
                    }}
                >
                    {days.map((day) => (
                        <div
                            key={day.date}
                            className={cn(
                                'flex flex-col items-center py-2 text-caption text-muted-foreground',
                                day.date === today &&
                                    'font-semibold text-primary',
                            )}
                            aria-current={
                                day.date === today ? 'date' : undefined
                            }
                        >
                            <span aria-hidden>
                                {weekdayInitial(day.weekday)}
                            </span>
                            <span className="tabular-nums">{day.day}</span>
                        </div>
                    ))}
                </div>
            </div>
            <div className="flex border-b">
                <div className="sticky left-0 z-10 flex w-44 shrink-0 items-center bg-card px-4 text-sm font-medium">
                    Fuera cada día
                </div>
                <div
                    className="grid flex-1"
                    style={{
                        gridTemplateColumns: `repeat(${days.length}, minmax(28px, 1fr))`,
                    }}
                >
                    {days.map((day) => (
                        <span
                            key={day.date}
                            className={cn(
                                'py-2 text-center text-caption tabular-nums',
                                (out[day.day - 1] ?? 0) === 0
                                    ? 'text-muted-foreground'
                                    : 'font-semibold',
                            )}
                        >
                            {out[day.day - 1] ?? 0}
                        </span>
                    ))}
                </div>
            </div>
        </>
    );
}
