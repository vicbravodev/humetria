<?php

declare(strict_types=1);

namespace App\Modules\Organization\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * A person was reactivated in their company. Dispatched once the change is committed.
 */
final readonly class EmployeeReactivated implements ShouldDispatchAfterCommit
{
    public function __construct(public string $tenantId, public string $employeeId) {}
}
