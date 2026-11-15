import WeekRibbon from '@/components/humetria/week-ribbon';
import { Badge } from '@/components/ui/badge';
import {
    restLabel,
    scheduleDays,
    scheduleRibbon,
} from '@/lib/attendance/schedule';
import { startLabel } from '@/lib/attendance/schedule-starts';
import type { WorkSchedule } from '@/types';

/** The week agreed as its ribbon with the days in a row and the rests; or that there is none and what is counted then. */
export default function ScheduleWeekCell({
    schedule,
}: {
    schedule: WorkSchedule | null;
}) {
    if (schedule === null) {
        return (
            <div className="flex flex-col items-start gap-1">
                <Badge variant="warning">Sin horario</Badge>
                <span className="text-caption text-muted-foreground">
                    Se cuentan 8 h al día
                </span>
            </div>
        );
    }

    return (
        <div className="flex items-center gap-3">
            <WeekRibbon days={scheduleRibbon(schedule.minutes)} />
            <div className="flex flex-col">
                <span className="tabular-nums">
                    {scheduleDays(schedule.minutes)}
                </span>
                <span className="text-caption text-muted-foreground">
                    {[
                        startLabel(schedule.minutes, schedule.starts),
                        restLabel(schedule.minutes),
                    ]
                        .filter((part) => part !== null)
                        .join(' · ')}
                </span>
            </div>
        </div>
    );
}
