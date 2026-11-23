<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Actions;

use App\Actions\RecordAudit;
use App\Modules\Attendance\Enums\ShiftType;
use App\Modules\Attendance\Enums\WorkMode;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Attendance\Support\ScheduleTimeline;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Contracts\Timezones;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Saves the agreed weekly schedule of a person of the company (criterio 2.6), set by RH, from a date on: it never erases
 * the one before, each day is read with the schedule in effect that day. One per start date: saving again for the same
 * date changes it. The date may be in the past, to correct a record; either way the audit trail keeps what was in effect
 * that day and what is now.
 */
final readonly class SaveSchedule
{
    public function __construct(private CurrentTenant $tenant, private Roster $roster, private Timezones $timezones, private RecordAudit $audit) {}

    /**
     * @param  array<int, int>  $minutesByWeekday  minutes agreed by ISO weekday, 1 Monday to 7 Sunday
     * @param  array<int, int|null>|null  $startsByWeekday  the minute of the day each ISO weekday starts; null for none
     * @param  CarbonImmutable|null  $validFrom  the first day it applies (its date only); null for today in the company's zone
     */
    public function handle(string $employeeId, ShiftType $shiftType, WorkMode $workMode, array $minutesByWeekday, ?array $startsByWeekday = null, ?CarbonImmutable $validFrom = null): AttendanceSchedule
    {
        if ($this->roster->find($employeeId) === null) {
            throw new InvalidArgumentException('The person is not in the company.');
        }

        $from = ($validFrom ?? CarbonImmutable::now($this->timezones->forTenant()))->toDateString();

        return DB::transaction(function () use ($employeeId, $shiftType, $workMode, $minutesByWeekday, $startsByWeekday, $from): AttendanceSchedule {
            $before = ScheduleTimeline::of([$employeeId])->on($employeeId, CarbonImmutable::parse($from));
            $beforeSnapshot = $before === null ? null : self::snapshot($before);

            $schedule = AttendanceSchedule::query()->updateOrCreate(['employee_id' => $employeeId, 'valid_from' => $from], [
                'tenant_id' => $this->tenant->id(),
                'shift_type' => $shiftType,
                'work_mode' => $workMode,
                'minutes_by_weekday' => $minutesByWeekday,
                'starts_by_weekday' => $startsByWeekday,
            ]);

            $this->audit->handle('attendance.schedule_saved', $schedule, [
                'employee_id' => $employeeId,
                'valid_from' => $from,
                'before' => $beforeSnapshot,
                'after' => self::snapshot($schedule),
            ]);
            Log::info('attendance.schedule_saved', ['schedule_id' => $schedule->id, 'employee_id' => $employeeId]);

            return $schedule;
        });
    }

    /**
     * The week as lists from Monday to Sunday, which the audit page reads as such.
     *
     * @return array{valid_from: string, shift_type: string, work_mode: string, minutes: list<int>, starts: list<int|null>|null}
     */
    private static function snapshot(AttendanceSchedule $schedule): array
    {
        return [
            'valid_from' => $schedule->valid_from->toDateString(),
            'shift_type' => $schedule->shift_type->value,
            'work_mode' => $schedule->work_mode->value,
            'minutes' => array_values($schedule->minutes_by_weekday),
            'starts' => $schedule->starts_by_weekday === null ? null : array_values($schedule->starts_by_weekday),
        ];
    }
}
