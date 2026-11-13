<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Actions;

use App\Modules\Attendance\Enums\ShiftType;
use App\Modules\Attendance\Enums\WorkMode;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/** Saves the agreed weekly schedule of a person of the company (criterio 2.6), set by RH. One per person. */
final readonly class SaveSchedule
{
    public function __construct(private CurrentTenant $tenant, private Roster $roster) {}

    /**
     * @param  array<int, int>  $minutesByWeekday  minutes agreed by ISO weekday, 1 Monday to 7 Sunday
     * @param  array<int, int|null>|null  $startsByWeekday  the minute of the day each ISO weekday starts; null for none
     */
    public function handle(string $employeeId, ShiftType $shiftType, WorkMode $workMode, array $minutesByWeekday, ?array $startsByWeekday = null): AttendanceSchedule
    {
        if ($this->roster->find($employeeId) === null) {
            throw new InvalidArgumentException('The person is not in the company.');
        }

        $schedule = AttendanceSchedule::query()->updateOrCreate(['employee_id' => $employeeId], [
            'tenant_id' => $this->tenant->id(),
            'shift_type' => $shiftType,
            'work_mode' => $workMode,
            'minutes_by_weekday' => $minutesByWeekday,
            'starts_by_weekday' => $startsByWeekday,
        ]);

        Log::info('attendance.schedule_saved', ['schedule_id' => $schedule->id, 'employee_id' => $employeeId]);

        return $schedule;
    }
}
