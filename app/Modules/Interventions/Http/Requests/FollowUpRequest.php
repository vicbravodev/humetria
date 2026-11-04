<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * HR schedules or records the follow-up evaluation of a row (num. 8.4 e). The rules about the result, the day and the
 * evaluation are the action's.
 */
final class FollowUpRequest extends FormRequest
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
            'follow_up_due_on' => ['nullable', 'date_format:Y-m-d'],
            'follow_up_done_on' => ['nullable', 'date_format:Y-m-d'],
            'follow_up_result' => ['nullable', 'string', 'max:4000'],
            'follow_up_campaign_id' => ['nullable', 'uuid'],
        ];
    }

    public function text(string $key): ?string
    {
        return $this->filled($key) ? $this->string($key)->toString() : null;
    }
}
