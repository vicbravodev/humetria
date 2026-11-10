<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Requests;

use App\Modules\Attendance\Support\ScheduleInput;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The same agreed schedule for several people at once, up to 100 (more than a page of the list): the people by id and the
 * schedule as ScheduleRequest takes it. Whether each is of the company is the controller's to say.
 */
final class BulkScheduleRequest extends FormRequest
{
    use ScheduleInput;

    public const int MAX_PEOPLE = 100;

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
            'employee_ids' => ['required', 'array', 'max:'.self::MAX_PEOPLE],
            'employee_ids.*' => ['required', 'uuid', 'distinct'],
            ...$this->scheduleRules(),
        ];
    }

    /**
     * @return list<string>
     */
    public function employeeIds(): array
    {
        return array_values(array_filter($this->array('employee_ids'), is_string(...)));
    }
}
