<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * What RH answered while writing the policy or a block of 5.7 with questions, the question it is on, or a section it rewrote.
 * Which answers and sections fit is checked against the template by the action.
 */
final class WritingRequest extends FormRequest
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
            'answers' => ['sometimes', 'array'],
            'step' => ['sometimes', 'integer'],
            'section' => ['sometimes', 'string', 'max:40'],
            'text' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array<array-key, mixed>|null */
    public function answers(): ?array
    {
        return $this->has('answers') ? $this->array('answers') : null;
    }

    /**
     * The section rewritten and its new text; an empty text puts back the one the answers make.
     *
     * @return array{section: string, text: string}|null
     */
    public function edit(): ?array
    {
        return $this->has('section') ? ['section' => $this->string('section')->toString(), 'text' => $this->string('text')->toString()] : null;
    }

    public function step(): ?int
    {
        return $this->has('step') ? $this->integer('step') : null;
    }

    public function userId(): int
    {
        return $this->user()->id ?? abort(403);
    }
}
