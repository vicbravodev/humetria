<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Data;

/**
 * The person of the session open in this request: their company and who they are, with the names their pages show.
 */
final readonly class WorkerIdentity
{
    public function __construct(
        public string $tenantId,
        public string $employeeId,
        public string $companyName,
        public string $personName,
    ) {}
}
