<?php

declare(strict_types=1);

namespace App\Modules\Seating\Actions;

use App\Modules\Seating\Enums\AssessmentStatus;
use App\Modules\Seating\Enums\DocumentKind;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Seating\Models\SeatingDocument;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Says in which minutes of the Comisión a signed assessment was recorded (Quinta B). It is one of the two marks a signed
 * assessment may still carry: the evidence itself does not change.
 */
final readonly class RecordAssessmentInMinutes
{
    /**
     * @throws ValidationException when the assessment is not signed or the document is not minutes of its center.
     */
    public function handle(RiskAssessment $assessment, ?SeatingDocument $document): RiskAssessment
    {
        if ($assessment->status !== AssessmentStatus::Signed) {
            throw ValidationException::withMessages(['document_id' => 'Solo una evaluación firmada se asienta en un acta.']);
        }

        if ($document === null || $document->kind !== DocumentKind::CommissionMinutes || $document->work_center_id !== $assessment->work_center_id) {
            throw ValidationException::withMessages(['document_id' => 'Elige un acta de la Comisión de este centro.']);
        }

        $assessment->update(['recorded_in_document_id' => $document->id]);
        Log::info('seating.assessment_recorded', ['assessment_id' => $assessment->id, 'document_id' => $document->id, 'work_center_id' => $assessment->work_center_id]);

        return $assessment;
    }
}
