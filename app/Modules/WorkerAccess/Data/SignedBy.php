<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Data;

/**
 * A signature the server verified: the person's key that made it and the person it belongs to.
 */
final readonly class SignedBy
{
    public function __construct(
        public string $credentialId,
        public string $employeeId,
    ) {}
}
