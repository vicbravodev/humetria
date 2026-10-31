<?php

declare(strict_types=1);

namespace App\Modules\Organization\Events;

/**
 * A person was deactivated in their company. Dispatched inside the transaction that deactivates them, so what its
 * listeners write commits, or rolls back, with it.
 */
final readonly class EmployeeDeactivated
{
    public function __construct(public string $tenantId, public string $employeeId) {}
}
