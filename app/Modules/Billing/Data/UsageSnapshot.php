<?php

declare(strict_types=1);

namespace App\Modules\Billing\Data;

/** Where a company stands with a meter in the current month. `granted` is the extra quota a person gave, already in `quota`. */
final readonly class UsageSnapshot
{
    public function __construct(
        public string $meter,
        public string $periodStart,
        public string $periodLabel,
        public int $used,
        public int $reserved,
        public int $quota,
        public int $granted,
    ) {}

    public function remaining(): int
    {
        return max(0, $this->quota - $this->used - $this->reserved);
    }
}
