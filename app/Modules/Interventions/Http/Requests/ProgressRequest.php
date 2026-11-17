<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Http\Requests;

use App\Modules\Interventions\Enums\ActionStatus;
use App\Modules\Interventions\Support\ProgramClock;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * HR records a step of an action: the day, the state it moves to and a note. The moves allowed are the action's to judge; a
 * cancellation is not a step (it has its own form and its reason).
 */
final class ProgressRequest extends FormRequest
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
            'status' => ['required', Rule::in([ActionStatus::Planned->value, ActionStatus::InProgress->value, ActionStatus::Done->value])],
            'recorded_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.ProgramClock::today()],
            'note' => ['nullable', 'string', 'max:2000'],
            // mimetypes reads the file's content, not its extension.
            'evidence' => ['nullable', 'file', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:'.config()->integer('interventions.max_document_kb')],
        ];
    }

    /**
     * The days as people read them («20 nov 2026»), never as the database writes them.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'recorded_on.date_format' => 'Elige una fecha válida.',
            'recorded_on.before_or_equal' => 'La fecha no puede ser posterior a hoy, '.ProgramClock::day(CarbonImmutable::parse(ProgramClock::today())).'.',
        ];
    }

    public function status(): ActionStatus
    {
        return ActionStatus::from($this->string('status')->toString());
    }

    /** Where the uploaded evidence is, if one came. */
    public function evidenceFile(): ?string
    {
        $file = $this->file('evidence');

        return $file instanceof UploadedFile ? $file->getPathname() : null;
    }

    public function note(): ?string
    {
        return $this->filled('note') ? $this->string('note')->toString() : null;
    }
}
