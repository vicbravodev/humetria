<?php

declare(strict_types=1);

namespace App\Modules\Billing\Data;

/** One month of a meter: what was used and the quota it had when last asked. */
final readonly class UsageHistoryRow
{
    public function __construct(
        public string $periodStart,
        public string $periodLabel,
        public int $used,
        public ?int $quota,
    ) {}
}
