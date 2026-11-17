import ProgressBar from '@/components/humetria/progress-bar';
import { modeLabel, shiftLabel, weekTotal } from '@/lib/attendance/schedule';
import { duration } from '@/lib/attendance/week-text';
import type { WeeklyMaximum, WorkSchedule } from '@/types';

/** The hours of the week agreed against the year's ordinary maximum, with a bar, and the shift and mode below. */
export default function ScheduleHoursCell({
    schedule,
    owner,
    weeklyMaximum,
}: {
    schedule: WorkSchedule | null;
    owner: string;
    weeklyMaximum: WeeklyMaximum;
}) {
    if (schedule === null) {
        return <span className="text-muted-foreground">—</span>;
    }

    const total = weekTotal(schedule.minutes);

    return (
        <div className="flex flex-col gap-1.5">
            <p>
                <span className="font-semibold tabular-nums">
                    {duration(total)}
                </span>{' '}
                <span className="text-caption text-muted-foreground">
                    de {duration(weeklyMaximum.minutes)}
                </span>
            </p>
            <ProgressBar
                value={total}
                max={weeklyMaximum.minutes}
                label={`Horas a la semana de ${owner} frente al máximo`}
            />
            <span className="text-caption text-muted-foreground">
                {`${shiftLabel(schedule.shiftType)} · ${modeLabel(schedule.workMode)}`}
            </span>
        </div>
    );
}
