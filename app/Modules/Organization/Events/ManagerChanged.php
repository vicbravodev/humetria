<?php

declare(strict_types=1);

namespace App\Modules\Organization\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * A person now answers to another manager, or to nobody. Dispatched once the change is committed: whoever has something
 * waiting for the manager they had can hand it to the one they have now.
 */
final readonly class ManagerChanged implements ShouldDispatchAfterCommit
{
    public function __construct(
        public string $tenantId,
        public string $employeeId,
        public ?string $fromManagerId,
        public ?string $toManagerId,
    ) {}
}
