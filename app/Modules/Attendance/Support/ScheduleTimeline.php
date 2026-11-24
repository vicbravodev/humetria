<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Models\AttendanceSchedule;
use Carbon\CarbonInterface;

/**
 * The schedules of some people over time: each day is read with the schedule in effect that day, the latest one that started
 * on or before it (criterio 2.6), so a past period gives the same hours today as it did then. A day before a person's first
 * schedule has none. Every schedule of the people comes in one query; the days are then resolved in memory.
 */
final readonly class ScheduleTimeline
{
    /**
     * @param  array<string, list<AttendanceSchedule>>  $byPerson  by employee id, the earliest start first
     */
    private function __construct(private array $byPerson) {}

    /**
     * @param  array<array-key, string>  $employeeIds
     */
    public static function of(array $employeeIds): self
    {
        return self::from(AttendanceSchedule::query()->among('employee_id', $employeeIds)->orderBy('valid_from')->get());
    }

    /** Every person of the company. */
    public static function ofCompany(): self
    {
        return self::from(AttendanceSchedule::query()->orderBy('valid_from')->get());
    }

    /**
     * @param  iterable<AttendanceSchedule>  $schedules  earliest start first
     */
    private static function from(iterable $schedules): self
    {
        $byPerson = [];
        foreach ($schedules as $schedule) {
            $byPerson[$schedule->employee_id][] = $schedule;
        }

        return new self($byPerson);
    }

    /** The schedule of the person in effect on the local date (its date only); null before their first. */
    public function on(string $employeeId, CarbonInterface $date): ?AttendanceSchedule
    {
        $day = $date->toDateString();
        $found = null;

        foreach ($this->byPerson[$employeeId] ?? [] as $schedule) {
            if ($schedule->valid_from->toDateString() > $day) {
                break;
            }

            $found = $schedule;
        }

        return $found;
    }

    /** Whether any schedule, of any date, sets start times: the only case where the company's tolerance is read. */
    public function hasStartTimes(): bool
    {
        return array_any($this->byPerson, fn (array $schedules): bool => array_any($schedules, fn (AttendanceSchedule $schedule): bool => $schedule->starts_by_weekday !== null));
    }

    /**
     * Who has a working day (minutes above zero) on the date by the schedule in effect then.
     *
     * @return list<string> employee ids
     */
    public function working(CarbonInterface $date): array
    {
        return array_values(array_filter(
            array_keys($this->byPerson),
            fn (string $employeeId): bool => ($this->on($employeeId, $date)?->minutes_by_weekday[$date->isoWeekday()] ?? 0) > 0,
        ));
    }
}
