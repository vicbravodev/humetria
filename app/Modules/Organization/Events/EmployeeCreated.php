<?php

declare(strict_types=1);

namespace App\Modules\Organization\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * A person joined a company's roster, by hand or by an import. Dispatched once the change is committed.
 */
final readonly class EmployeeCreated implements ShouldDispatchAfterCommit
{
    public function __construct(public string $tenantId, public string $employeeId, public string $workCenterId) {}
}
