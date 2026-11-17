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
}
