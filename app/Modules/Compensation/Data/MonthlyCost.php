<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Data;

use Carbon\CarbonImmutable;

/** The cost of one of the first 12 months, in cents. `month` is the day that month starts on, counted from the effective date. */
final readonly class MonthlyCost
{
    public function __construct(public CarbonImmutable $month, public int $beforeCents, public int $afterCents) {}

    public function deltaCents(): int
    {
        return $this->afterCents - $this->beforeCents;
    }
}
