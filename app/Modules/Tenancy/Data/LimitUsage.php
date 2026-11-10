<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Data;

/**
 * What a company has used of one limit of its plan. `over` is a company that already had more than the limit when it was set:
 * it keeps what it has and cannot add.
 */
final readonly class LimitUsage
{
    public function __construct(
        public string $resource,
        public string $label,
        public int $used,
        public int $amount,
        public bool $over,
    ) {}

    public function remaining(): int
    {
        return max(0, $this->amount - $this->used);
    }
}
