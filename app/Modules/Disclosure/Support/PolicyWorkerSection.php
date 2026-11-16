<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use App\Modules\Disclosure\Models\Acknowledgment;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Contracts\WorkerSection;
use App\Modules\WorkerAccess\Data\WorkerIdentity;
use App\Modules\WorkerAccess\Data\WorkerSectionLink;

/**
 * «Política por confirmar» in the person's start, while a policy asked of the person is not confirmed.
 */
final readonly class PolicyWorkerSection implements WorkerSection
{
    public function __construct(private Companies $companies) {}

    public function for(WorkerIdentity $person): ?WorkerSectionLink
    {
        $company = $this->companies->current();

        if ($company->stopped('nom035')) {
            return null;
        }

        $waiting = Acknowledgment::query()->where('employee_id', $person->employeeId)->whereNull('acknowledged_at')->count();

        return $waiting === 0 ? null : new WorkerSectionLink('policy', 'Política por confirmar', 'Lea la política de su centro y confirme que la leyó.', route('portal.policy.show', $company->slug), $waiting);
    }
}
