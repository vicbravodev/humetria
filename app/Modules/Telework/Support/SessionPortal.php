<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Telework\Enums\TeleworkerStatus;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Contracts\WorkerSession;
use App\Modules\WorkerAccess\Data\WorkerIdentity;

/**
 * The telework portal of the person of the session (WorkerAccess): who they are, in which company, and their open telework
 * period if they have one. The session already holds only for an active person of a company that may be used.
 */
final readonly class SessionPortal
{
    public function __construct(private WorkerSession $session, private Companies $companies) {}

    /** The portal of the person of this request's session; null for a visitor. */
    public function current(): ?OpenedPortal
    {
        $person = $this->session->current();

        return $person === null ? null : $this->for($person);
    }

    public function for(WorkerIdentity $person): OpenedPortal
    {
        $company = $this->companies->current();
        $teleworker = Teleworker::query()->where('employee_id', $person->employeeId)->where('status', '!=', TeleworkerStatus::Ended)->first(['id']);

        return new OpenedPortal($person->tenantId, $person->employeeId, $teleworker?->id, $person->companyName, $person->personName, ! $company->stopped('nom037'), $company);
    }
}
