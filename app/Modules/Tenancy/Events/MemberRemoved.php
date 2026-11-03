<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Events;

/**
 * A person lost their access to a company (their account stays). Dispatched inside the transaction that removes them, so
 * what its listeners clear commits, or rolls back, with it.
 */
final readonly class MemberRemoved
{
    public function __construct(public string $tenantId, public int $userId) {}
}
