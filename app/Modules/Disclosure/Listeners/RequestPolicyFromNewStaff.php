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
use Illuminate\Queue\InteractsWithQueue;

/**
 * Whoever joins a work center after its policy was published (a new hire, a reactivated or a moved-in person) is asked to
 * confirm the version in force there. Asking twice is harmless: a version asks once per person. Queued, after the commit,
 * entering the company the event names: an import of many rows must not wait for one request per row.
 */
final class RequestPolicyFromNewStaff implements ShouldQueue, ShouldQueueAfterCommit
{
    use InteractsWithQueue;

    /** The job carries its own tries: Horizon's supervisors run with one, and a failure here would lose the request and its mail. */
    public int $tries = 3;

    /** @var list<int> Seconds before the second and the third try. */
    public array $backoff = [60, 300];

    public function __construct(private Companies $companies, private Roster $roster, private RequestAcknowledgments $request) {}

    public function handle(EmployeeCreated|EmployeeReactivated|EmployeeMoved $event): void
    {
        if ($this->companies->enter($event->tenantId)->stopped('nom035')) {
            return;
        }

        $person = $this->roster->find($event->employeeId);

        if ($person !== null && $person->active) {
            $this->request->forEmployee($person->id, $person->workCenterId, retrying: $this->attempts() > 1);
        }
    }
}
