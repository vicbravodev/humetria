<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Events;

/** A member's direct permissions in a company changed. Dispatched inside the transaction that changes them. */
final readonly class MemberPermissionsChanged
{
    public function __construct(public string $tenantId, public int $userId) {}
}
