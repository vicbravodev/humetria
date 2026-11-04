<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Which work centers a block of 5.7 is published to: every one in use, or the ones named. The text itself is checked by the
 * action.
 */
final class ContentPublishRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-disclosure') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'all_centers' => ['boolean'],
            'work_center_ids' => ['array'],
            'work_center_ids.*' => ['uuid'],
        ];
    }

    /**
     * The centers named, or null for every center in use.
     *
     * @return list<string>|null
     */
    public function workCenterIds(): ?array
    {
        return $this->boolean('all_centers') ? null : array_values(array_filter($this->array('work_center_ids'), is_string(...)));
    }

    public function userId(): int
    {
        return $this->user()->id ?? abort(403);
    }
}
