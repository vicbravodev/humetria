<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Listeners;

use App\Modules\Disclosure\Actions\RequestAcknowledgments;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Events\EmployeeCreated;
use App\Modules\Organization\Events\EmployeeMoved;
use App\Modules\Organization\Events\EmployeeReactivated;
use App\Modules\Tenancy\Contracts\Companies;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

/**
 * Whoever joins a work center after its policy was published (a new hire, a reactivated or a moved-in person) is asked to
 * confirm the version in force there. Asking twice is harmless: a version asks once per person. Queued, after the commit,
 * entering the company the event names: an import of many rows must not wait for one request per row.
 */
final class RequestPolicyFromNewStaff implements ShouldQueue, ShouldQueueAfterCommit
{
    public function __construct(private Companies $companies, private Roster $roster, private RequestAcknowledgments $request) {}

    public function handle(EmployeeCreated|EmployeeReactivated|EmployeeMoved $event): void
    {
        $this->companies->enter($event->tenantId);
        $person = $this->roster->find($event->employeeId);

        if ($person !== null && $person->active) {
            $this->request->forEmployee($person->id, $person->workCenterId);
        }
    }
}
