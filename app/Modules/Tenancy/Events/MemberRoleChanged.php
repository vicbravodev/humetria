<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Events;

/**
 * A member's role in a company changed to `$role`, the role's internal name: a template key or `custom_<uuid>`.
 * Dispatched inside the transaction that changes it, so what its listeners end commits, or rolls back, with it.
 */
final readonly class MemberRoleChanged
{
    public function __construct(public string $tenantId, public int $userId, public string $role) {}
}
