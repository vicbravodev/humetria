<?php

declare(strict_types=1);

namespace App\Modules\Seating\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * What a person answers in their portal: footwear and discomfort (1 to 3, as the DOF's tables), an optional note, and that
 * they accepted the privacy notice. Without the acceptance nothing is saved.
 */
final class PersonInputRequest extends FormRequest
{
    /** The portal link is the credential: the controller opens it. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'footwear' => ['required', 'integer', 'between:1,3'],
            'discomfort' => ['required', 'integer', 'between:1,3'],
            'note' => ['nullable', 'string', 'max:1000'],
            'accepted' => ['accepted'],
        ];
    }

    public function note(): ?string
    {
        $note = trim($this->string('note')->toString());

        return $note === '' ? null : $note;
    }
}
