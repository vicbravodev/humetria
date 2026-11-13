<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Telework\Contracts\PortalSection;
use App\Modules\Telework\Data\PortalIdentity;
use App\Modules\Telework\Data\PortalSectionLink;
use App\Modules\Tenancy\Contracts\Companies;

/**
 * «Mi puesto de pie» in the home of the portal, for whoever holds a standing position today.
 */
final readonly class SeatingPortalSection implements PortalSection
{
    public function __construct(private Companies $companies) {}

    public function for(PortalIdentity $person, string $token): ?PortalSectionLink
    {
        if ($this->companies->current()->stopped('seating') || ! PositionAssignment::query()->where('employee_id', $person->employeeId)->whereNull('ends_on')->exists()) {
            return null;
        }

        return new PortalSectionLink('seating', 'Mi puesto de pie', 'Cuéntenos cómo es su trabajo de pie y confirme las medidas para cuidarlo.', route('portal.seating.show', $token));
    }
}
