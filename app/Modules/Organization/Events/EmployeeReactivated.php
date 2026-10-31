<?php

declare(strict_types=1);

namespace App\Modules\Organization\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * A person was reactivated in their company, in the work center they belong to. Dispatched once the change is committed.
 */
final readonly class EmployeeReactivated implements ShouldDispatchAfterCommit
{
    public function __construct(public string $tenantId, public string $employeeId, public string $workCenterId) {}
}
