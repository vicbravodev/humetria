<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Events;

/**
 * A member's permissions in a company changed, given directly or through their role. Dispatched inside the transaction that
 * changes them.
 */
final readonly class MemberPermissionsChanged
{
    public function __construct(public string $tenantId, public int $userId) {}
}
