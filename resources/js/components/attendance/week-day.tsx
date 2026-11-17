import { ChevronRight, Plus } from 'lucide-react';
import WeekMark from '@/components/attendance/week-mark';
import { Badge } from '@/components/ui/badge';
import { lateLabel } from '@/lib/attendance/late';
import type { WeekDay as Day, WeekMark as Mark } from '@/lib/attendance/week';
import { dayNeedsLook, dayRange, dayShort } from '@/lib/attendance/week-fold';
import { duration } from '@/lib/attendance/week-text';

/**
 * One day of the week, folded (board «Asistencia: su semana»): its name, the range of its marks and its hours; open, its
 * marks on a line of time with the pencil that asks for each correction, its flags, and one «Me faltó una marca». It opens
 * by itself only when something in it asks to be looked at.
 */
export default function WeekDay({
    day,
    pending,
    onAsk,
}: {
    day: Day;
    /** The marks with a request waiting for RH: none more of them. */
    pending: string[];
    /** Asks for the correction of a mark of this day, or of one missing (null). */
    onAsk: (mark: Mark | null) => void;
}) {
    const late = lateLabel(day.lateMinutes);

    return (
        <li className="border-t first:border-t-0">
            <details className="group" open={dayNeedsLook(day)}>
                <summary className="flex min-h-14 cursor-pointer list-none items-center gap-2.5 rounded-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring [&::-webkit-details-marker]:hidden">
                    <span className="w-16 shrink-0 font-semibold whitespace-nowrap">
                        {dayShort(day.date)}
                    </span>
                    <span className="flex-1 text-meta text-muted-foreground">
                        {dayRange(day)}
                    </span>
                    <span className="font-semibold tabular-nums">
                        {duration(day.workedMinutes)}
                    </span>
                    <ChevronRight
                        aria-hidden
                        className="size-5 shrink-0 text-muted-foreground transition-transform duration-200 ease-out group-open:rotate-90 motion-reduce:transition-none"
                    />
                </summary>
                <div className="flex flex-col gap-0.5 pt-1 pb-3.5 pl-1">
                    {(late !== null || day.missingEnd) && (
                        <div className="flex flex-wrap gap-2 pb-1 pl-[22px]">
                            {late !== null && (
                                <Badge variant="warning">{late}</Badge>
                            )}
                            {day.missingEnd && (
                                <Badge variant="warning">
                                    Sin salida marcada
                                </Badge>
                            )}
                        </div>
                    )}
                    {day.marks.length > 0 && (
                        <ul className="flex flex-col">
                            {day.marks.map((mark) => (
                                <WeekMark
                                    key={mark.id}
                                    mark={mark}
                                    asked={pending.includes(mark.id)}
                                    onAsk={() => onAsk(mark)}
                                />
                            ))}
                        </ul>
                    )}
                    <button
                        type="button"
                        className="mt-1.5 ml-[22px] inline-flex min-h-11 items-center gap-2 self-start rounded-lg border border-input bg-card px-3.5 text-base font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        onClick={() => onAsk(null)}
                    >
                        <Plus aria-hidden className="size-5 text-primary" />
                        Me faltó una marca
                    </button>
                </div>
            </details>
        </li>
    );
}
