<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Actions;

use App\Modules\Attendance\Data\DayHours;
use App\Modules\Attendance\Events\DayClosed;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Attendance\Support\EffectiveMarks;
use App\Modules\Attendance\Support\WeekPeriod;
use App\Modules\Attendance\Support\WorkedDay;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Closes a person's local day (the design's Semana y día): its marks are those of the days that start on it, even when
 * they end after midnight. It stores nothing, so closing it again gives the same day: the hours are always counted from
 * the marks. A day with no end mark is flagged (DayClosed::missingEnd), never closed for the person. Nothing to close:
 * no marks on a day the schedule does not make a working day.
 */
final readonly class CloseDay
{
    public function handle(string $employeeId, CarbonImmutable $localDate): ?DayHours
    {
        $date = CarbonImmutable::parse($localDate->toDateString(), config()->string('app.display_timezone'));
        $marks = EffectiveMarks::byDay($employeeId, WeekPeriod::containing($date))[$date->toDateString()] ?? [];
        $schedule = AttendanceSchedule::query()->where('employee_id', $employeeId)->first();

        if ($marks === [] && ($schedule?->minutes_by_weekday[$date->isoWeekday()] ?? 0) === 0) {
            return null;
        }

        $missingEnd = WorkedDay::missingEnd($marks);
        event(new DayClosed($employeeId, $date->toDateString(), $missingEnd));
        Log::info('attendance.day_closed', ['employee_id' => $employeeId, 'date' => $date->toDateString(), 'missing_end' => $missingEnd]);

        return WorkedDay::hours($date, $marks, $schedule);
    }
}
