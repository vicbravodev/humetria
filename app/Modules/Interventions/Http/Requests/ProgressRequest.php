<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Http\Requests;

use App\Modules\Interventions\Enums\ActionStatus;
use App\Modules\Interventions\Support\ProgramClock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * HR records a step of an action: the day, the state it moves to and a note. The moves allowed are the action's to judge; a
 * cancellation is not a step (it has its own form and its reason).
 */
final class ProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-interventions') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([ActionStatus::Planned->value, ActionStatus::InProgress->value, ActionStatus::Done->value])],
            'recorded_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.ProgramClock::today()],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function status(): ActionStatus
    {
        return ActionStatus::from($this->string('status')->toString());
    }

    public function note(): ?string
    {
        return $this->filled('note') ? $this->string('note')->toString() : null;
    }
}
