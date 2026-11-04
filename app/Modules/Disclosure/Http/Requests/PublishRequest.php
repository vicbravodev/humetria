<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * What HR decides when publishing the policy: which work centers (all in use, or the ones named), which incisos of 5.1 it
 * contemplates and that they reviewed it. The rules of the policy itself are the action's to judge.
 */
final class PublishRequest extends FormRequest
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
            'covers' => ['array'],
            'covers.*' => ['in:a,b,c'],
            'reviewed' => ['accepted'],
        ];
    }

    public function userId(): int
    {
        return $this->user()->id ?? abort(403);
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

    /**
     * @return list<string>
     */
    public function covers(): array
    {
        return array_values(array_filter($this->array('covers'), is_string(...)));
    }
}
