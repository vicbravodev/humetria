import WeekRibbon from '@/components/humetria/week-ribbon';
import ProgressBar from '@/components/humetria/progress-bar';
import { scheduleRibbon } from '@/lib/attendance/schedule';
import { duration } from '@/lib/attendance/week-text';
import type { WeekMinutes, WeeklyMaximum } from '@/types';

/** The week being written, drawn as the ribbon with its total against the year's maximum (board A5). */
export default function SchedulePreview({
    minutes,
    total,
    weeklyMaximum,
}: {
    minutes: WeekMinutes;
    total: number;
    weeklyMaximum: WeeklyMaximum;
}) {
    return (
        <div className="flex items-center gap-5 rounded-lg bg-muted/50 p-4">
            <WeekRibbon days={scheduleRibbon(minutes)} size="md" />
            <div className="flex min-w-0 flex-1 flex-col gap-1.5">
                <p>
                    <span className="text-2xl leading-8 font-semibold tabular-nums">
                        {duration(total)}
                    </span>{' '}
                    <span className="text-muted-foreground">a la semana</span>
                </p>
                <ProgressBar
                    value={total}
                    max={weeklyMaximum.minutes}
                    label="Horas a la semana frente al máximo del año"
                />
                <p className="text-sm text-muted-foreground">
                    Máximo ordinario de {weeklyMaximum.year}:{' '}
                    {duration(weeklyMaximum.minutes)}.
                </p>
            </div>
        </div>
    );
}
