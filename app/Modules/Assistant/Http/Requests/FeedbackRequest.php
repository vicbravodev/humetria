<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** The thumb on an answer of Humi: which answer, up or down, and a reason from a closed list. No free text. */
final class FeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('use-assistant');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'message_id' => ['required', 'uuid'],
            'rating' => ['required', Rule::in(['up', 'down'])],
            'reason' => ['nullable', Rule::in(['incorrect', 'incomplete', 'unclear', 'other'])],
        ];
    }

    public function messageId(): string
    {
        return $this->string('message_id')->toString();
    }

    public function rating(): string
    {
        return $this->string('rating')->toString();
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->toString() : null;
    }
}
