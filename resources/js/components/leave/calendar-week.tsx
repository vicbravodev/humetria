import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import Initials from '@/components/humetria/initials';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    weekdayInitial,
    weekOf,
    weeksOf,
    weekPeople,
} from '@/lib/leave/calendar';
import type { CalendarDay } from '@/lib/leave/calendar';
import { dateSpan } from '@/lib/leave/format';
import { cn } from '@/lib/utils';
import type { LeaveCalendarPerson } from '@/types';

/**
 * The calendar on a phone: the week is the unit. A strip of its seven days with how many are out each, and under it who
 * is away that week with their days in words. No grid of thirty columns.
 */
export default function CalendarWeek({
    days,
    today,
    out,
    people,
}: {
    days: CalendarDay[];
    today: string;
    out: number[];
    people: LeaveCalendarPerson[];
}) {
    const weeks = weeksOf(days);
    const start = weeks.findIndex(
        (week) => week[0]?.date === weekOf(days, today)[0]?.date,
    );
    const [index, setIndex] = useState(Math.max(0, start));
    const week = weeks[index] ?? [];
    const first = week[0]?.date ?? '';
    const last = week[week.length - 1]?.date ?? '';
    const who = weekPeople(people, week);

    return (
        <section aria-label="Semana" className="flex flex-col gap-4 card p-4">
            <div className="flex items-center justify-between gap-2">
                <Button
                    variant="outline"
                    size="icon"
                    disabled={index === 0}
                    onClick={() => setIndex(index - 1)}
                    aria-label="Semana anterior"
                >
                    <ChevronLeft aria-hidden />
                </Button>
                <h3 className="text-center font-semibold">
                    {dateSpan(first, last)}
                </h3>
                <Button
                    variant="outline"
                    size="icon"
                    disabled={index >= weeks.length - 1}
                    onClick={() => setIndex(index + 1)}
                    aria-label="Semana siguiente"
                >
                    <ChevronRight aria-hidden />
                </Button>
            </div>
            <ol className="grid grid-cols-7 gap-1">
                {week.map((day) => (
                    <li
                        key={day.date}
                        aria-label={`${day.day}: ${out[day.day - 1] ?? 0} fuera`}
                        className={cn(
                            'flex flex-col items-center rounded-lg border py-1.5',
                            (out[day.day - 1] ?? 0) > 0 && 'bg-accent',
                            day.date === today && 'border-2 border-primary',
                        )}
                    >
                        <span className="text-caption text-muted-foreground">
                            {weekdayInitial(day.weekday)}
                        </span>
                        <span className="text-sm tabular-nums">{day.day}</span>
                        <span className="text-sm font-medium tabular-nums">
                            {out[day.day - 1] ?? 0}
                        </span>
                    </li>
                ))}
            </ol>
            {who.length === 0 ? (
                <p className="text-meta text-muted-foreground">
                    Nadie falta esta semana.
                </p>
            ) : (
                <ul className="flex flex-col gap-1">
                    {who.map((person) => (
                        <li
                            key={person.id}
                            className="flex min-h-11 items-center gap-3"
                        >
                            <Initials name={person.name} className="size-8" />
                            <span className="min-w-0 flex-1 truncate font-medium">
                                {person.name}
                            </span>
                            <span className="tabular-nums">{person.text}</span>
                            {person.pending && (
                                <Badge variant="warning">Por responder</Badge>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
