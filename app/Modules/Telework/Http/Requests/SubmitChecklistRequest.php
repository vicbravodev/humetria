<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * The answers of a checklist, as the person's portal sends them. Their content is judged by `AnswerRules`; this only
 * keeps the shape and the size sane. No account: the portal link is the credential (checked by the controller).
 */
final class SubmitChecklistRequest extends FormRequest
{
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
            'consent' => ['nullable', 'boolean'],
            'answers' => ['required', 'array'],
            'answers.*' => ['nullable', 'string', 'max:20'],
            'risk_notes' => ['nullable', 'array'],
            'risk_notes.*' => ['nullable', 'string', 'max:2000'],
            'special_needs' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, ?string> */
    public function answers(): array
    {
        $answers = [];

        foreach ($this->array('answers') as $question => $answer) {
            $answers[(string) $question] = is_string($answer) ? $answer : null;
        }

        return $answers;
    }

    /** @return array<string, string> */
    public function riskNotes(): array
    {
        $notes = [];

        foreach ($this->array('risk_notes') as $question => $note) {
            $notes[(string) $question] = is_string($note) ? $note : '';
        }

        return $notes;
    }

    /**
     * The photos, by the question they belong to (or `general`). Each file travels in its own flat field,
     * `photo_{question}_{n}`: a nested name (`photos[id][0]`) is not read the same by every server.
     *
     * @return array<string, list<UploadedFile>>
     */
    public function photos(): array
    {
        $photos = [];

        foreach ($this->allFiles() as $field => $file) {
            if ($file instanceof UploadedFile && preg_match('/^photo_([0-9a-f-]{36}|general)_\d{1,3}$/', (string) $field, $matches) === 1) {
                $photos[$matches[1]][] = $file;
            }
        }

        return $photos;
    }
}
