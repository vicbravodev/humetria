<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

use App\Modules\Seating\Enums\AssessmentStatus;
use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Models\RiskAcknowledgment;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Telework\Contracts\PortalSection;
use App\Modules\Telework\Data\PortalIdentity;
use App\Modules\Telework\Data\PortalSectionLink;
use App\Modules\Tenancy\Contracts\Companies;

/**
 * «Mi puesto de pie» in the home of the portal, for whoever holds a standing position today, with the assessment waiting for their confirmation counted.
 */
final readonly class SeatingPortalSection implements PortalSection
{
    public function __construct(private Companies $companies) {}

    public function for(PortalIdentity $person, string $token): ?PortalSectionLink
    {
        if ($this->companies->current()->stopped('seating') || ! PositionAssignment::query()->where('employee_id', $person->employeeId)->whereNull('ends_on')->exists()) {
            return null;
        }

        // What waits for the person: the current signed assessment they have not confirmed. Answering the two questions is voluntary, so it is not a pending.
        $current = RiskAssessment::query()->where('employee_id', $person->employeeId)->where('status', AssessmentStatus::Signed)->latest('signed_at')->first(['id']);
        $waiting = $current !== null && ! RiskAcknowledgment::query()->where('assessment_id', $current->id)->exists();

        return new PortalSectionLink('seating', 'Mi puesto de pie', 'Cuéntenos cómo es su trabajo de pie y confirme las medidas para cuidarlo.', route('portal.seating.show', $token), $waiting ? 1 : null);
    }
}
