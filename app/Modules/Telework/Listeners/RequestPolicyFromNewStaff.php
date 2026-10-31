<?php

declare(strict_types=1);

namespace App\Modules\Telework\Listeners;

use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Events\EmployeeCreated;
use App\Modules\Organization\Events\EmployeeMoved;
use App\Modules\Organization\Events\EmployeeReactivated;
use App\Modules\Telework\Actions\RequestAcknowledgements;
use App\Modules\Telework\Enums\DocumentKind;
use App\Modules\Tenancy\Contracts\Companies;

/**
 * Whoever joins a work center after its policy was published (a new hire, a reactivated or a moved-in person) is asked to
 * acknowledge the policy in force there. Asking twice is harmless: a version asks once per person.
 */
final readonly class RequestPolicyFromNewStaff
{
    public function __construct(private Companies $companies, private Roster $roster, private RequestAcknowledgements $request) {}

    public function handle(EmployeeCreated|EmployeeReactivated|EmployeeMoved $event): void
    {
        $this->companies->enter($event->tenantId);
        $person = $this->roster->find($event->employeeId);

        if ($person !== null && $person->active) {
            $this->request->forEmployee($person->id, $person->workCenterId, DocumentKind::Policy);
        }
    }
}
