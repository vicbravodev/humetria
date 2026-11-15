<?php

declare(strict_types=1);

namespace App\Modules\Results\Support;

use App\Modules\Results\Enums\HowTold;
use App\Modules\Results\Models\ScreeningReferral;
use App\Modules\Telework\Contracts\PortalSection;
use App\Modules\Telework\Data\PortalIdentity;
use App\Modules\Telework\Data\PortalSectionLink;
use App\Modules\Tenancy\Contracts\Companies;

/**
 * «Apoyo» in the home of the portal, for whoever Recursos Humanos told, through the portal, that the company offers it.
 */
final readonly class SupportPortalSection implements PortalSection
{
    public function __construct(private Companies $companies) {}

    public function for(PortalIdentity $person, string $token): ?PortalSectionLink
    {
        if ($this->companies->current()->stopped('nom035') || ! ScreeningReferral::query()->where('employee_id', $person->employeeId)->where('told', HowTold::Portal)->exists()) {
            return null;
        }

        return new PortalSectionLink('support', 'Apoyo', 'Un mensaje de su empresa sobre el apoyo que le ofrece. Es confidencial.', route('portal.support.show', $token), 1);
    }
}
