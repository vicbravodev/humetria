<?php

declare(strict_types=1);

namespace App\Modules\Organization\Support;

use App\Modules\Telework\Contracts\PortalSection;
use App\Modules\Telework\Data\PortalIdentity;
use App\Modules\Telework\Data\PortalSectionLink;
use App\Modules\Tenancy\Contracts\Companies;

/**
 * «Mi equipo» in the home of the portal: the manager, the peers and the reports of the person, for any company with the org chart.
 */
final readonly class TeamPortalSection implements PortalSection
{
    public function __construct(private Companies $companies) {}

    public function for(PortalIdentity $person, string $token): ?PortalSectionLink
    {
        if ($this->companies->current()->stopped('org_chart')) {
            return null;
        }

        return new PortalSectionLink('team', 'Mi equipo', 'Su jefe directo, sus compañeros y quienes le reportan.', route('portal.team.show', $token));
    }
}
