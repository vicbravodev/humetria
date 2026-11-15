<?php

declare(strict_types=1);

namespace App\Modules\Telework\Contracts;

/**
 * RH forwarding a person the link of their portal, which the person may have lost. Never a new link: it is the one they have.
 */
interface PortalLinks
{
    /**
     * Emails the person of the current company their link. False, with nothing sent, when they have no email or are not an
     * active person of the company.
     */
    public function sendTo(string $employeeId): bool;
}
