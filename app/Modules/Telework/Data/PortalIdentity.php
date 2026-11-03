<?php

declare(strict_types=1);

namespace App\Modules\Telework\Data;

/**
 * Whose a valid portal link is: the person, their company (already entered) and the names the page greets them with.
 */
final readonly class PortalIdentity
{
    public function __construct(
        public string $tenantId,
        public string $employeeId,
        public string $company,
        public string $name,
    ) {}
}
