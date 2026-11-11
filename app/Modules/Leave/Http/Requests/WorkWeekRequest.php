<?php

declare(strict_types=1);

namespace App\Modules\Leave\Http\Requests;

use App\Modules\Leave\Enums\WorkWeek;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The working week of the «Ley» policy, set by owner and hr. Nothing else of the setting is read from here: the day the
 * company started tracking is not edited.
 */
final class WorkWeekRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-vacations') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['statutory_work_week' => ['required', Rule::enum(WorkWeek::class)]];
    }

    public function week(): WorkWeek
    {
        return WorkWeek::from($this->string('statutory_work_week')->toString());
    }
}
