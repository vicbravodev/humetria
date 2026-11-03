<?php

declare(strict_types=1);

namespace App\Modules\Seating\Listeners;

use App\Modules\Organization\Events\EmployeeDeactivated;
use App\Modules\Organization\Events\EmployeeMoved;
use App\Modules\Seating\Actions\EndAssignmentsOfPerson;
use App\Modules\Tenancy\Contracts\Companies;

/**
 * Someone who left the company, or now works in another center, no longer holds their position there: it ends today.
 */
final readonly class EndOnDeactivationOrMove
{
    public function __construct(private Companies $companies, private EndAssignmentsOfPerson $end) {}

    public function handle(EmployeeDeactivated|EmployeeMoved $event): void
    {
        $this->companies->enter($event->tenantId);
        $this->end->handle($event->employeeId);
    }
}
