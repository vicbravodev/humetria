<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

/**
 * A portal link that works: whose it is, in which company, and their open telework period if they have one.
 */
final readonly class OpenedPortal
{
    public function __construct(
        public string $tenantId,
        public string $employeeId,
        public ?string $teleworkerId,
        public string $company,
        public string $name,
    ) {}
}
