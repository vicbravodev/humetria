<?php

declare(strict_types=1);

namespace App\Modules\Billing\Data;

use Carbon\CarbonImmutable;

/** A company's plan as the company reads it: its name, since when and until when it renews. No price, no balance. */
final readonly class PlanSummary
{
    public function __construct(public string $name, public CarbonImmutable $since, public CarbonImmutable $renewsOn) {}
}
