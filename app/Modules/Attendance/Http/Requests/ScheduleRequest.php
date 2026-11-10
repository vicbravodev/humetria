<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Requests;

use App\Modules\Attendance\Support\ScheduleInput;
use Illuminate\Foundation\Http\FormRequest;

/** A person's agreed weekly schedule (criterio 2.6), set by owner or hr; see ScheduleInput for what it carries. */
final class ScheduleRequest extends FormRequest
{
    use ScheduleInput;

    public function authorize(): bool
    {
        return $this->user()?->can('manage-attendance') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->scheduleRules();
    }
}
