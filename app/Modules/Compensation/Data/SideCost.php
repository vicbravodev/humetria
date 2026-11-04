<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Data;

/**
 * What a cost unit costs on one side of a scenario (before or after the change), by concept id ("benefit:christmas_bonus").
 * `months` are the 12 monthly amounts of each concept (they add up to its year); `month0` the amount of the first month
 * as a full year, which is what recurs.
 */
final readonly class SideCost
{
    /**
     * @param  array<string, list<int>>  $months
     * @param  array<string, int>  $month0
     * @param  list<string>  $uncalculated  contribution lines this side could not calculate
     */
    public function __construct(public array $months, public array $month0, public array $uncalculated) {}

    public function yearCents(): int
    {
        return array_sum(array_map(array_sum(...), $this->months));
    }
}
