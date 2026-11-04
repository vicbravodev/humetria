<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Data;

/** A cost unit costed before and after the scenario. */
final readonly class UnitCost
{
    public function __construct(public CostUnit $unit, public SideCost $before, public SideCost $after) {}
}
