<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Requests;

use App\Modules\Attendance\Enums\ShiftType;
use App\Modules\Attendance\Enums\WorkMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A person's agreed weekly schedule (criterio 2.6): their kind of shift, where they work and the minutes of each ISO weekday,
 * 1 Monday to 7 Sunday, all seven and no other: 0 is a rest day, 720 (12 h, art. 68) the most. Rule::in, not Rule::enum:
 * the enum rule ignores the custom messages of lang/es/validation.php.
 */
final class ScheduleRequest extends FormRequest
{
    private const array WEEKDAYS = ['1', '2', '3', '4', '5', '6', '7'];

    public function authorize(): bool
    {
        return $this->user()?->can('manage-attendance') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'shift_type' => ['required', Rule::in(array_column(ShiftType::cases(), 'value'))],
            'work_mode' => ['required', Rule::in(array_column(WorkMode::cases(), 'value'))],
            'minutes' => ['required', 'array:'.implode(',', self::WEEKDAYS), 'required_array_keys:'.implode(',', self::WEEKDAYS)],
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
