<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use App\Modules\Leave\Enums\Tranche;

/**
 * Days of a request that come out of one tranche of one period.
 */
final readonly class AllocationLine
{
    public function __construct(public string $entitlementId, public Tranche $tranche, public int $days) {}
}
