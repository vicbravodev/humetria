<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Data;

/**
 * How many keys of a person are in use and how many were revoked.
 */
final readonly class CredentialCounts
{
    public function __construct(
        public int $active,
        public int $revoked,
    ) {}
}
