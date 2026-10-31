import AnswerDayBar from '@/components/campaigns/answer-day-bar';
import { busiestDay, daysLeft, monthOf } from '@/lib/evaluation';
import type { AnswersByDay } from '@/types';

/**
 * How many people finished each day, from launch to the last day (pantalla Eval-Activa). Until the minimum group has
 * answered there are no days to show: one day with one person would tell when they answered.
 */
export default function AnswersByDayCard({
    answers,
}: {
    answers: AnswersByDay;
}) {
    const days = answers.days;

    return (
        <article className="flex flex-col gap-4 rounded-xl border bg-card p-6 md:col-span-2">
            <div className="flex flex-col gap-0.5">
                <h2 className="text-lg leading-7 font-semibold">
                    Respuestas por día
                </h2>
                <p className="text-sm text-muted-foreground">
                    {days === null
                        ? `Se muestran cuando terminen al menos ${answers.minimum} personas, para que ningún día delate quién contestó.`
                        : `${daysLeft(days)}. Cada persona cuenta el día en que terminó.`}
                </p>
            </div>
            {days !== null && (
                <>
                    <ol className="flex items-end gap-1 pt-1.5 sm:gap-2">
                        {days.map((day) => (
                            <AnswerDayBar
                                key={day.date}
                                day={day}
                                busiest={busiestDay(days)}
                            />
                        ))}
                    </ol>
                    <div
                        aria-hidden
                        className="flex justify-between text-xs text-muted-foreground"
                    >
                        <span>{monthOf(days[0]?.date ?? '')}</span>
                        <span>{monthOf(days.at(-1)?.date ?? '')}</span>
                    </div>
                </>
            )}
        </article>
    );
}
