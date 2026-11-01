<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Requests;

use App\Modules\Telework\Enums\TrainingModality;
use App\Modules\Telework\Enums\TrainingTopic;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * A training given: where, which day (not in the future), how, the 8.1 topics, who attended and, optionally, the signed
 * list of attendance as a PDF. Whether the center is in use and the attendees belong to it is checked by the action.
 */
final class TrainingRequest extends FormRequest
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
            'work_center_id' => ['required', 'string'],
            'held_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.CarbonImmutable::now(config()->string('app.display_timezone'))->toDateString()],
            'modality' => ['required', Rule::enum(TrainingModality::class)],
            'topics' => ['required', 'array', 'min:1'],
            'topics.*' => ['string', Rule::enum(TrainingTopic::class)],
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['string'],
            // mimetypes reads the file's content, not its extension.
            'evidence' => ['nullable', 'file', 'mimetypes:application/pdf', 'max:'.config()->integer('telework.max_document_kb')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['work_center_id' => 'centro de trabajo', 'held_on' => 'fecha', 'modality' => 'modalidad', 'topics' => 'temas', 'employee_ids' => 'asistentes', 'evidence' => 'lista de asistencia'];
    }

    public function modality(): TrainingModality
    {
        return $this->enum('modality', TrainingModality::class) ?? TrainingModality::Mixed;
    }

    public function heldOn(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $this->string('held_on')->toString())?->startOfDay() ?? CarbonImmutable::now();
    }

    /** @return list<TrainingTopic> */
    public function topics(): array
    {
        return array_values(array_filter(array_map(fn (mixed $topic): ?TrainingTopic => is_string($topic) ? TrainingTopic::tryFrom($topic) : null, $this->array('topics'))));
    }

    /** @return list<string> */
    public function employeeIds(): array
    {
        return array_values(array_filter($this->array('employee_ids'), is_string(...)));
    }

    /** Where the uploaded PDF is, when there is one. */
    public function evidenceFile(): ?string
    {
        $file = $this->file('evidence');

        return $file instanceof UploadedFile ? $file->getPathname() : null;
    }
}
