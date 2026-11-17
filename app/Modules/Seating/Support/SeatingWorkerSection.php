<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

use App\Modules\Seating\Enums\AssessmentStatus;
use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Models\RiskAcknowledgment;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Contracts\WorkerSection;
use App\Modules\WorkerAccess\Data\WorkerIdentity;
use App\Modules\WorkerAccess\Data\WorkerSectionLink;

/**
 * «Mi puesto de pie» in the person's start, for whoever holds a standing position today, with the assessment waiting for their confirmation counted.
 */
final readonly class SeatingWorkerSection implements WorkerSection
{
    public function __construct(private Companies $companies) {}

    public function for(WorkerIdentity $person): ?WorkerSectionLink
    {
        $company = $this->companies->current();

        if ($company->stopped('seating') || ! PositionAssignment::query()->where('employee_id', $person->employeeId)->whereNull('ends_on')->exists()) {
            return null;
        }

        // What waits for the person: the current signed assessment they have not confirmed. Answering the two questions is voluntary, so it is not a pending.
        $current = RiskAssessment::query()->where('employee_id', $person->employeeId)->where('status', AssessmentStatus::Signed)->latest('signed_at')->first(['id']);
        $waiting = $current !== null && ! RiskAcknowledgment::query()->where('assessment_id', $current->id)->exists();

        return new WorkerSectionLink('seating', 'Mi puesto de pie', 'Su evaluación y sus preguntas', route('portal.seating.show', $company->slug), $waiting ? 1 : null);
    }
}
