<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Data;

/**
 * The roles of a company in counts: how many of its own it has (of `maxOwnRoles`), how many people hold each role (only the
 * roles someone holds, templates first) and how many people have permissions besides their role.
 */
final readonly class AccessOverview
{
    /**
     * @param  list<RoleCount>  $roles
     */
    public function __construct(
        public int $ownRoles,
        public int $maxOwnRoles,
        public array $roles,
        public int $withExtras,
    ) {}
}
