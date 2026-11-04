<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The day the link of the page stops opening, or nothing to take the expiry away. Whether the day is in range is the action's.
 */
final class ExpiryRequest extends FormRequest
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
        return ['expires_on' => ['nullable', 'date_format:Y-m-d']];
    }

    public function day(): ?string
    {
        return $this->filled('expires_on') ? $this->string('expires_on')->toString() : null;
    }

    public function userId(): int
    {
        return $this->user()->id ?? abort(403);
    }
}
