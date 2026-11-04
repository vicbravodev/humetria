<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Data;

/** The seniority premium the company would owe today to those who qualify, before and after the scenario, in cents. */
final readonly class SeniorityEstimate
{
    /** @param  int  $people  people who qualify (15 years of service or more) and have a salary */
    public function __construct(public int $beforeCents, public int $afterCents, public int $people) {}

    public function deltaCents(): int
    {
        return $this->afterCents - $this->beforeCents;
    }
}
