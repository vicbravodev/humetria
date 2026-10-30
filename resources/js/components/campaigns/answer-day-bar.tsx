import { barHeight, dayAnswersText, dayOf, monthOf } from '@/lib/evaluation';
import type { AnswerDay } from '@/types';

/** One day of "Respuestas por día": its bar, or a dashed outline while it is still to come, and its number. */
export default function AnswerDayBar({
    day,
    busiest,
}: {
    day: AnswerDay;
    busiest: number;
}) {
    return (
        <li className="flex min-w-0 flex-1 flex-col items-center gap-1.5">
            <span className="flex h-[120px] w-full max-w-[30px] items-end pt-5">
                {day.answers === null ? (
                    <span className="h-[26px] w-full rounded-md border-[1.5px] border-dashed" />
                ) : (
                    <span
                        className="w-full rounded-[6px_6px_2px_2px] bg-primary"
                        style={{ height: barHeight(day.answers, busiest) }}
                    />
                )}
            </span>
            <span
                aria-hidden
                className="text-xs text-muted-foreground tabular-nums"
            >
                {dayOf(day.date)}
            </span>
            <span className="sr-only">
                {dayOf(day.date)} de {monthOf(day.date)}:{' '}
                {dayAnswersText(day.answers)}
            </span>
        </li>
    );
}
