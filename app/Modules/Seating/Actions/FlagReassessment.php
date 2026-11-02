<?php

declare(strict_types=1);

namespace App\Modules\Seating\Actions;

use App\Modules\Seating\Enums\AssessmentStatus;
use App\Modules\Seating\Models\PersonInput;
use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Models\RiskAssessment;
use Illuminate\Support\Facades\Log;

/**
 * Marks the current signed assessment of a person «por reevaluar» when something it was built on changed: their position,
 * their footwear, or more discomfort than it recorded. It only sets the mark: the signed assessment stays current and final
 * until another is signed (criterio C-07).
 */
final readonly class FlagReassessment
{
    public function handle(string $employeeId): bool
    {
        $current = RiskAssessment::query()->where('employee_id', $employeeId)->where('status', AssessmentStatus::Signed)->latest('signed_at')->first();

        if ($current === null || $current->reassessment_due || ! $this->changed($current)) {
            return false;
        }

        $current->update(['reassessment_due' => true]);
        Log::info('seating.reassessment_flagged', ['assessment_id' => $current->id, 'work_center_id' => $current->work_center_id]);

        return true;
    }

    private function changed(RiskAssessment $assessment): bool
    {
        $position = PositionAssignment::query()->where('employee_id', $assessment->employee_id)->whereNull('ends_on')->first();

        if ($position !== null && $position->position_id !== $assessment->position_id) {
            return true;
        }

        $input = PersonInput::query()->where('employee_id', $assessment->employee_id)->where('submitted_at', '>', $assessment->signed_at)->latest('submitted_at')->first();

        return $input !== null && ($input->discomfort > ($assessment->scores['discomfort'] ?? 1) || $input->footwear !== ($assessment->scores['footwear'] ?? $input->footwear));
    }
}
