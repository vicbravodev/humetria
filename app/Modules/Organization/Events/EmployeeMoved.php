<?php

declare(strict_types=1);

namespace App\Modules\Organization\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * A person now works in another work center of their company. Dispatched once the change is committed.
 */
final readonly class EmployeeMoved implements ShouldDispatchAfterCommit
{
    public function __construct(
        public string $tenantId,
        public string $employeeId,
        public string $fromWorkCenterId,
        public string $toWorkCenterId,
    ) {}
}
