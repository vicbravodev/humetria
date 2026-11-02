<?php

declare(strict_types=1);

namespace App\Modules\Telework\Contracts;

use App\Modules\Telework\Data\PortalIdentity;

/**
 * Who a personal portal link belongs to. The link is the person's, not a tool's: other tools (Ley Silla) open their own
 * pages under the same token instead of issuing another.
 */
interface PortalAccess
{
    /**
     * Opens the company of the link and says whose it is; null, without saying why, when the link is not valid (made up,
     * replaced, of a person who left, or of a company that may not be used).
     */
    public function open(string $token): ?PortalIdentity;

    /**
     * The personal token of an employee of the current company, to build the link to a page of theirs. It is the same one
     * each time (issuing it never breaks the link the person has) and it is never logged.
     */
    public function token(string $employeeId): string;
}
