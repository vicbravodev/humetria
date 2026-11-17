import { WEEKDAYS } from '@/lib/attendance/schedule';
import { draftInputs } from '@/lib/attendance/schedule-draft';
import type { DraftWeek } from '@/lib/attendance/schedule-draft';
import type { WorkSchedule } from '@/types';

/**
 * What the form sends, whatever is folded: where they work, and the minutes and start of each day (`minutes[d]`,
 * `starts[d]`, a rest day with no start).
 */
export default function ScheduleWeekInputs({
    week,
    mode,
}: {
    week: DraftWeek;
    mode: WorkSchedule['workMode'];
}) {
    const inputs = draftInputs(week);

    return (
        <>
            <input type="hidden" name="work_mode" value={mode} />
            {WEEKDAYS.map(({ day }) => (
                <span key={day} hidden>
                    <input
                        type="hidden"
                        name={`minutes[${day}]`}
                        value={inputs.minutes[day] ?? ''}
                    />
                    <input
                        type="hidden"
                        name={`starts[${day}]`}
                        value={inputs.starts[day] ?? ''}
                    />
                </span>
            ))}
        </>
    );
}
