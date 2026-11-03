<?php

declare(strict_types=1);

namespace App\Modules\Seating\Http\Requests;

use App\Modules\Seating\Data\MeasureData;
use App\Modules\Seating\Enums\MeasureKind;
use App\Modules\Seating\Enums\MeasureStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * RH registers a measure (kind, position or assessment) or follows one up (status, owner, due date, evidence). The kind and
 * what it is tied to are only read when it is created; the action checks that they belong to the center.
 */
final class MeasureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-seating') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');

        return [
            'kind' => [$creating ? 'required' : 'prohibited', Rule::enum(MeasureKind::class)],
            'note' => ['nullable', 'string', 'max:1000', Rule::requiredIf(fn (): bool => $creating && $this->input('kind') === MeasureKind::Other->value)],
            'position_id' => ['nullable', 'uuid'],
            'assessment_id' => ['nullable', 'uuid'],
            'status' => [$creating ? 'nullable' : 'required', Rule::enum(MeasureStatus::class)],
            'owner_user_id' => ['nullable', 'integer'],
            'due_on' => ['nullable', 'date_format:Y-m-d'],
            // mimetypes reads the file's content, not its extension.
            'evidence' => ['nullable', 'file', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:'.config()->integer('seating.max_document_kb')],
        ];
    }

    public function toData(?string $id, string $workCenterId): MeasureData
    {
        $note = trim($this->string('note')->toString());

        return new MeasureData(
            id: $id,
            workCenterId: $workCenterId,
            kind: $this->filled('kind') ? MeasureKind::from($this->string('kind')->toString()) : null,
            note: $note === '' ? null : $note,
            assessmentId: $this->filled('assessment_id') ? $this->string('assessment_id')->toString() : null,
            positionId: $this->filled('position_id') ? $this->string('position_id')->toString() : null,
            status: $this->filled('status') ? MeasureStatus::from($this->string('status')->toString()) : MeasureStatus::Planned,
            ownerUserId: $this->filled('owner_user_id') ? $this->integer('owner_user_id') : null,
            dueOn: $this->filled('due_on') ? $this->string('due_on')->toString() : null,
        );
    }

    /** Where the uploaded evidence is, if one came. */
    public function evidenceFile(): ?string
    {
        $file = $this->file('evidence');

        return $file instanceof UploadedFile ? $file->getPathname() : null;
    }
}
