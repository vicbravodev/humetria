<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Events;

/**
 * What one of a company's own roles gives changed: `$userIds` are everyone who holds the role, told in a single event so a
 * listener can read them together. `$roleName` is the role's internal name (`custom_<uuid>`). Dispatched inside the
 * transaction that changes it.
 */
final readonly class RolePermissionsChanged
{
    /**
     * @param  list<int>  $userIds
     */
    public function __construct(public string $tenantId, public string $roleName, public array $userIds) {}
}
