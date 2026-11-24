<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Contracts;

/**
 * Who belongs to a company, asked from outside it: before anyone is signed in there is no current company to ask.
 */
interface MemberLookup
{
    /** The user with that email (case aside) who is a member of the company; null when nobody is. */
    public function userIdByEmail(string $tenantId, string $email): ?int;

    /**
     * Every permission the person has in the company, by their role and given directly; empty when they hold none.
     *
     * @return list<string>
     */
    public function permissionsOf(string $tenantId, int $userId): array;

    /**
     * The same for many people at once, in a fixed number of queries: each person's permissions, by user id.
     *
     * @param  list<int>  $userIds
     * @return array<int, list<string>>
     */
    public function permissionsOfMany(string $tenantId, array $userIds): array;

    /** Whether the person is the company's owner (its management); false when they hold any other role or none. */
    public function isOwner(string $tenantId, int $userId): bool;
}
