<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Contracts\WorkerSection;
use App\Modules\WorkerAccess\Data\WorkerIdentity;
use App\Modules\WorkerAccess\Data\WorkerSectionLink;

/**
 * «Mis vacaciones» in the person's start; for a manager it says how many requests of their team wait for an answer.
 */
final readonly class VacationsWorkerSection implements WorkerSection
{
    public function __construct(private Companies $companies) {}

    public function for(WorkerIdentity $person): ?WorkerSectionLink
    {
        $company = $this->companies->current();

        if ($company->stopped('vacations') || ! LeaveSetting::query()->exists()) {
            return null;
        }

        $waiting = PortalTeamPages::pendingFor($person->employeeId);

        return new WorkerSectionLink(
            'vacations',
            'Mis vacaciones',
            $waiting === 0 ? 'Sus días y solicitudes' : 'Su saldo, sus solicitudes y las de su equipo que esperan su respuesta.',
            route('portal.vacations.show', $company->slug),
            $waiting === 0 ? null : $waiting,
        );
    }
}
