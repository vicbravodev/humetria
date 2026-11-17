<?php

declare(strict_types=1);

namespace App\Modules\Organization\Support;

use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Contracts\WorkerSection;
use App\Modules\WorkerAccess\Data\WorkerIdentity;
use App\Modules\WorkerAccess\Data\WorkerSectionLink;

/**
 * «Mi equipo» in the person's start: the manager, the peers and the reports of the person, for any company with the org chart.
 */
final readonly class TeamWorkerSection implements WorkerSection
{
    public function __construct(private Companies $companies) {}

    public function for(WorkerIdentity $person): ?WorkerSectionLink
    {
        $company = $this->companies->current();

        if ($company->stopped('org_chart')) {
            return null;
        }

        return new WorkerSectionLink('team', 'Mi equipo', 'Su jefe y sus compañeros', route('portal.team.show', $company->slug));
    }
}
