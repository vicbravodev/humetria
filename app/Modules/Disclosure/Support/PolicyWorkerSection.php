<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use App\Modules\Disclosure\Models\Acknowledgment;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Contracts\WorkerSection;
use App\Modules\WorkerAccess\Data\WorkerIdentity;
use App\Modules\WorkerAccess\Data\WorkerSectionLink;

/**
 * «Política por confirmar» in the person's start, while the version in force in their work center now, asked of them, is not
 * confirmed: the page they land on shows that one, so an older version, or the one of a center they left, is not counted.
 */
final readonly class PolicyWorkerSection implements WorkerSection
{
    public function __construct(private Companies $companies, private Roster $roster, private PolicyStanding $standing) {}

    public function for(WorkerIdentity $person): ?WorkerSectionLink
    {
        $company = $this->companies->current();

        if ($company->stopped('nom035')) {
            return null;
        }

        $center = $this->roster->find($person->employeeId)->workCenterId ?? '';
        $inForce = $this->standing->inForce([$center])[$center] ?? null;
        $waiting = $inForce === null ? 0 : Acknowledgment::query()->where('document_id', $inForce->documentId)->where('employee_id', $person->employeeId)->whereNull('acknowledged_at')->count();

        return $waiting === 0 ? null : new WorkerSectionLink('policy', 'Política por confirmar', 'Léala y confirme que la leyó', route('portal.policy.show', $company->slug), $waiting);
    }
}
