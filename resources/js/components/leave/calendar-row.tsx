import Initials from '@/components/humetria/initials';
import { dateSpan } from '@/lib/leave/format';
import { isRestDay, stretchColumns } from '@/lib/leave/calendar';
import type { CalendarDay } from '@/lib/leave/calendar';
import { cn } from '@/lib/utils';
import type { LeaveCalendarPerson, LeaveCalendarStretch } from '@/types';

function stretchLabel(name: string, stretch: LeaveCalendarStretch): string {
    const what = stretch.kind === 'vacation' ? 'Vacaciones' : 'Permiso';

    return `${name}: ${what}${stretch.pending ? ' por responder' : ''}, ${dateSpan(stretch.from, stretch.to)}`;
}

/** A person of the calendar: a cell per day, shaded on the days nobody works, with their stretches over it. */
export default function CalendarRow({
    person,
    days,
    holidays,
}: {
    person: LeaveCalendarPerson;
    days: CalendarDay[];
    holidays: string[];
}) {
    return (
        <li className="flex border-b last:border-b-0">
            <div className="sticky left-0 z-10 flex w-44 shrink-0 items-center gap-2 bg-card px-4 py-2">
                <Initials name={person.name} className="size-7" />
                <span className="truncate text-sm font-medium">
                    {person.name}
                </span>
            </div>
            <div
                className="grid flex-1 items-center"
                style={{
                    gridTemplateColumns: `repeat(${days.length}, minmax(28px, 1fr))`,
                }}
            >
                {days.map((day) => (
                    <span
                        key={day.date}
                        aria-hidden
                        style={{ gridColumn: day.day, gridRow: 1 }}
                        className={cn(
                            'h-full min-h-11',
                            isRestDay(day, holidays) && 'bg-muted',
                        )}
                    />
                ))}
                {person.absences.map((stretch) => {
                    const { column, span } = stretchColumns(
                        stretch.from,
                        stretch.to,
                    );

                    return (
                        <span
                            key={`${stretch.from}-${stretch.pending}`}
                            role="img"
                            aria-label={stretchLabel(person.name, stretch)}
                            style={{
                                gridColumn: `${column} / span ${span}`,
                                gridRow: 1,
                            }}
                            className={cn(
                                'z-0 mx-px h-5 rounded-md',
                                stretch.pending
                                    ? 'border border-dashed'
                                    : stretch.kind === 'vacation'
                                      ? 'bg-primary'
                                      : 'bg-muted-foreground',
                                stretch.pending &&
                                    (stretch.kind === 'vacation'
                                        ? 'border-primary'
                                        : 'border-muted-foreground'),
                            )}
                        />
                    );
                })}
            </div>
        </li>
    );
}
