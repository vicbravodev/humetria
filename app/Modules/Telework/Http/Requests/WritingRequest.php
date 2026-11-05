<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * What RH answered while writing a NOM-037 document with questions, and the question it is on. Which answers fit is
 * checked against the document's questions by the action.
 */
final class WritingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-telework') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'answers' => ['sometimes', 'array'],
            'step' => ['sometimes', 'integer'],
        ];
    }

    /** @return array<array-key, mixed>|null */
    public function answers(): ?array
    {
        return $this->has('answers') ? $this->array('answers') : null;
    }

    public function step(): ?int
    {
        return $this->has('step') ? $this->integer('step') : null;
    }
}
