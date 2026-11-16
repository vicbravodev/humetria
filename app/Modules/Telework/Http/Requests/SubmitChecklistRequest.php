<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

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
        $rules = [
            'consent' => ['nullable', 'boolean'],
            'answers' => ['required', 'array'],
            'answers.*' => ['nullable', 'string', 'max:20'],
            'risk_notes' => ['nullable', 'array'],
            'risk_notes.*' => ['nullable', 'string', 'max:2000'],
            'special_needs' => ['nullable', 'string', 'max:2000'],
        ];

        // Each file is capped on its own field, before PhotoStore reads it.
        foreach (array_keys($this->allFiles()) as $field) {
            if (preg_match('/^photo_([0-9a-f-]{36}|general)_\d{1,3}$/', (string) $field) === 1) {
                $rules[(string) $field] = ['file', 'max:'.config()->integer('telework.max_photo_kb')];
            }
        }

        return $rules;
    }

    /** @return list<Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $most = config()->integer('telework.max_photos_per_group');

            if (array_filter($this->photos(), fn (array $group): bool => count($group) > $most) !== []) {
                $validator->errors()->add('photos', "Puede agregar hasta {$most} fotos por pregunta y {$most} del lugar.");
            }

            $total = config()->integer('telework.max_photos_per_checklist');

            if (array_sum(array_map(count(...), $this->photos())) > $total) {
                $validator->errors()->add('photos', "Puede enviar hasta {$total} fotos en total.");
            }
        }];
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
