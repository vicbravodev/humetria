<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

/**
 * A portal link that works: whose it is, in which company, and their open telework period if they have one. `$telework` says whether the company has NOM-037: the link is the person's,
 * and the pages of this tool answer 404 without it.
 */
final readonly class OpenedPortal
{
    public function __construct(
        public string $tenantId,
        public string $employeeId,
        public ?string $teleworkerId,
        public string $company,
        public string $name,
        public bool $telework = true,
    ) {}
}
