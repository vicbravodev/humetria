<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Models\AttendanceMark;
use Carbon\CarbonImmutable;

/**
 * What a person's week still lacks to be confirmed (criterio 5.2): the days they had to work and left without marks, or
 * with a start and no end. One calculation for «Mi semana» (what it shows) and for ConfirmWeek (what it blocks). It reads
 * nothing: everything comes in.
 *
 * A day is covered, and no mark is asked for, when the person has no schedule in effect that day (none yet, or a day
 * before their first one: WorkedDay holds them to 8 hours every day of the week, which would block every Sunday), when
 * the schedule in effect that day rests it (each day with its own, so a change in the middle of the week splits it),
 * when it is a holiday, when it is before the person's hire date, when they are away with approved vacations or a leave,
 * or when it has not ended: its local date, in the zone of the week, is today or later. Otherwise the day is required, and
 * it is uncovered when it has no marks or its last mark is not an end, except yesterday's: a night shift that started
 * then may still be running (the block `day_open` holds the week until its end is marked).
 */
final readonly class WeekCompleteness
{
    /**
     * @param  list<array{required: bool, uncovered: bool}>  $days  Monday to Sunday
     * @param  list<string>  $dates  Y-m-d of each day, the same order
     */
    private function __construct(private array $days, private array $dates) {}

    /**
     * @param  array<string, list<AttendanceMark>>  $byDay  the week's effective marks by local date (EffectiveMarks::byDay)
     * @param  array<int, string>  $away  the days away with approved vacations or a leave, by place in the week (AbsentDays)
     * @param  list<string>  $holidays  Y-m-d, the company's (Absences::holidaysBetween)
     */
    public static function of(WeekPeriod $week, array $byDay, ScheduleTimeline $schedules, string $employeeId, array $away, array $holidays, ?CarbonImmutable $hiredOn): self
    {
        $now = CarbonImmutable::now($week->timezone());
        $today = $now->toDateString();
        // A start with no end is still a shift in progress, not a missing end, until the day after the one it started (a night).
        $yesterday = $now->subDay()->toDateString();
        $days = [];
        $dates = [];

        foreach (range(0, 6) as $offset) {
            $date = $week->start()->addDays($offset);
            $key = $date->toDateString();
            $schedule = $schedules->on($employeeId, $date);
            $covered = $schedule === null
                || ($schedule->minutes_by_weekday[$date->isoWeekday()] ?? 0) === 0
                || in_array($key, $holidays, true)
                || ($hiredOn !== null && $key < $hiredOn->toDateString())
                || isset($away[$offset])
                || $key >= $today;
            $marks = $byDay[$key] ?? [];

            $dates[] = $key;
            $days[] = ['required' => ! $covered, 'uncovered' => ! $covered && ($marks === [] || ($key < $yesterday && WorkedDay::missingEnd($marks)))];
        }

        return new self($days, $dates);
    }

    /**
     * @return list<string> Y-m-d, in order
     */
    public function uncoveredDays(): array
    {
        return array_values(array_filter($this->dates, fn (string $date, int $offset): bool => $this->days[$offset]['uncovered'], ARRAY_FILTER_USE_BOTH));
    }

    /**
     * @param  int  $offset  0 Monday to 6 Sunday
     * @return array{required: bool, uncovered: bool}
     */
    public function day(int $offset): array
    {
        return $this->days[$offset] ?? ['required' => false, 'uncovered' => false];
    }
}
