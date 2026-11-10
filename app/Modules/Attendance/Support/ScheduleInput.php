<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Enums\ShiftType;
use App\Modules\Attendance\Enums\WorkMode;
use Illuminate\Validation\Rule;

/**
 * An agreed weekly schedule as RH's form sends it (criterio 2.6), for the requests that take one: their kind of shift, where
 * they work and the minutes of each ISO weekday, 1 Monday to 7 Sunday, all seven and no other: 0 is a rest day, 720 (12 h,
 * art. 68) the most. Rule::in, not Rule::enum: the enum rule ignores the custom messages of lang/es/validation.php.
 */
trait ScheduleInput
{
    /**
     * @return array<string, mixed>
     */
    private function scheduleRules(): array
    {
        $weekdays = ['1', '2', '3', '4', '5', '6', '7'];

        return [
            'shift_type' => ['required', Rule::in(array_column(ShiftType::cases(), 'value'))],
            'work_mode' => ['required', Rule::in(array_column(WorkMode::cases(), 'value'))],
            'minutes' => ['required', 'array:'.implode(',', $weekdays), 'required_array_keys:'.implode(',', $weekdays)],
            'minutes.*' => ['required', 'integer', 'between:0,720'],
        ];
    }

    public function shiftType(): ShiftType
    {
        return $this->enum('shift_type', ShiftType::class) ?? ShiftType::Day;
    }

    public function workMode(): WorkMode
    {
        return $this->enum('work_mode', WorkMode::class) ?? WorkMode::Onsite;
    }

    /**
     * @return array<int, int> minutes by ISO weekday
     */
    public function minutesByWeekday(): array
    {
        $minutes = [];
        foreach (range(1, 7) as $weekday) {
            $minutes[$weekday] = $this->integer("minutes.{$weekday}");
        }

        return $minutes;
    }
}
