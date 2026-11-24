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

    /** The internal name of the person's role in the company (a template key or `custom_<uuid>`); null when they have none. */
    public function roleOf(string $tenantId, int $userId): ?string;
}
