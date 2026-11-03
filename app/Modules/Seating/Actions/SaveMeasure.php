<?php

declare(strict_types=1);

namespace App\Modules\Seating\Actions;

use App\Modules\Seating\Data\MeasureData;
use App\Modules\Seating\Enums\MeasureStatus;
use App\Modules\Seating\Models\PreventiveMeasure;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Seating\Support\EvidenceStore;
use App\Modules\Seating\Support\SeatingFiles;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Contracts\Members;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * RH registers a measure of the action plan or follows one up: its status, who answers for it, its due date and its
 * evidence. The assessment and the position must be of the center and the owner must belong to the company. A cancelled
 * measure is not edited (criterio C-17); a new evidence replaces the old file once the change is committed.
 */
final readonly class SaveMeasure
{
    public function __construct(private CurrentTenant $tenant, private Members $members, private EvidenceStore $evidence, private SeatingFiles $files) {}

    /**
     * @throws ValidationException
     */
    public function handle(MeasureData $data, ?string $evidenceFile, int $userId): PreventiveMeasure
    {
        $existing = $data->id === null ? null : PreventiveMeasure::query()->findOrFail($data->id);

        if ($existing?->status === MeasureStatus::Cancelled) {
            throw ValidationException::withMessages(['status' => 'Esta medida está cancelada y no se edita.']);
        }

        $this->check($data, $existing === null);

        $tenantId = $this->tenant->id();
        $stored = $evidenceFile === null ? null : $this->evidence->store($evidenceFile, "measures/{$tenantId}/{$data->workCenterId}");

        try {
            [$measure, $replaced] = DB::transaction(function () use ($data, $existing, $stored, $tenantId, $userId): array {
                $measure = $existing ?? new PreventiveMeasure;
                $previous = $measure->evidence_path;

                if ($existing === null) {
                    $measure->fill([
                        'tenant_id' => $tenantId,
                        'work_center_id' => $data->workCenterId,
                        'assessment_id' => $data->assessmentId,
                        'position_id' => $data->positionId,
                        'kind' => $data->kind,
                        'note' => $data->note,
                        'proposed_by' => $userId,
                    ]);
                }

                $measure->fill(['status' => $data->status, 'owner_user_id' => $data->ownerUserId, 'due_on' => $data->dueOn, 'evidence_path' => $stored ?? $previous])->save();
                Log::info('seating.measure_saved', ['measure_id' => $measure->id, 'work_center_id' => $measure->work_center_id, 'kind' => $measure->kind->value, 'status' => $measure->status->value]);

                return [$measure, $stored !== null ? $previous : null];
            });
        } catch (Throwable $failure) {
            if ($stored !== null) {
                $this->files->delete($stored);
            }

            throw $failure;
        }

        if ($replaced !== null) {
            $this->files->delete($replaced);
        }

        return $measure;
    }

    private function check(MeasureData $data, bool $isNew): void
    {
        $errors = [];

        if ($isNew && $data->assessmentId === null && $data->positionId === null) {
            $errors['position_id'] = 'Elige el puesto o la evaluación a la que corresponde la medida.';
        }

        if ($isNew && $data->positionId !== null && ! StandingPosition::query()->where('id', $data->positionId)->where('work_center_id', $data->workCenterId)->exists()) {
            $errors['position_id'] = 'Ese puesto no es de este centro de trabajo.';
        }

        if ($isNew && $data->assessmentId !== null && ! RiskAssessment::query()->where('id', $data->assessmentId)->where('work_center_id', $data->workCenterId)->exists()) {
            $errors['assessment_id'] = 'Esa evaluación no es de este centro de trabajo.';
        }

        if ($data->ownerUserId !== null && $this->members->find($data->ownerUserId) === null) {
            $errors['owner_user_id'] = 'Esa persona no pertenece a la empresa.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
