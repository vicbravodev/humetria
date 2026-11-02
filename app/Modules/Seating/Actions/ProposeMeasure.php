<?php

declare(strict_types=1);

namespace App\Modules\Seating\Actions;

use App\Modules\Seating\Enums\MeasureKind;
use App\Modules\Seating\Enums\MeasureStatus;
use App\Modules\Seating\Models\PreventiveMeasure;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\Log;

/**
 * The Comisión proposes a measure from an assessment (criterio C-10). It starts planned: RH decides who answers for it, by
 * when, and follows it up.
 */
final readonly class ProposeMeasure
{
    public function __construct(private CurrentTenant $tenant) {}

    public function handle(RiskAssessment $assessment, MeasureKind $kind, ?string $note, int $userId): PreventiveMeasure
    {
        $measure = PreventiveMeasure::query()->create([
            'tenant_id' => $this->tenant->id(),
            'work_center_id' => $assessment->work_center_id,
            'assessment_id' => $assessment->id,
            'position_id' => $assessment->position_id,
            'kind' => $kind,
            'note' => $note,
            'status' => MeasureStatus::Planned,
            'proposed_by' => $userId,
        ]);

        Log::info('seating.measure_saved', ['measure_id' => $measure->id, 'work_center_id' => $measure->work_center_id, 'kind' => $kind->value, 'status' => 'planned']);

        return $measure;
    }
}
