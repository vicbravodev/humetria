<?php

declare(strict_types=1);

namespace App\Modules\Results\Support;

use App\Modules\Results\Enums\HowTold;
use App\Modules\Results\Models\ScreeningReferral;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Contracts\WorkerSection;
use App\Modules\WorkerAccess\Data\WorkerIdentity;
use App\Modules\WorkerAccess\Data\WorkerSectionLink;

/**
 * «Apoyo» in the person's start, for whoever Recursos Humanos told, through the portal, that the company offers it.
 */
final readonly class SupportWorkerSection implements WorkerSection
{
    public function __construct(private Companies $companies) {}

    public function for(WorkerIdentity $person): ?WorkerSectionLink
    {
        $company = $this->companies->current();

        if ($company->stopped('nom035') || ! ScreeningReferral::query()->where('employee_id', $person->employeeId)->where('told', HowTold::Portal)->exists()) {
            return null;
        }

        return new WorkerSectionLink('support', 'Apoyo', 'Un mensaje confidencial', route('portal.support.show', $company->slug), 1);
    }
}
