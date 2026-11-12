<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Data;

/** A counter that had drifted from what is really there, and what it was set to. */
final readonly class LimitCorrection
{
    public function __construct(
        public string $tenantId,
        public string $resource,
        public int $recorded,
        public int $actual,
    ) {}
}
