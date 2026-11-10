<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use Carbon\CarbonImmutable;

/**
 * What a person has left of one vacation period, by tranche, with the dates of its window.
 */
final readonly class PeriodBalance
{
    public function __construct(
        public string $entitlementId,
        public CarbonImmutable $anniversaryOn,
        public CarbonImmutable $enjoyBy,
        public CarbonImmutable $prescribesOn,
        public int $statutoryLeft,
        public int $extraLeft,
    ) {}

    public function left(): int
    {
        return $this->statutoryLeft + $this->extraLeft;
    }
}
