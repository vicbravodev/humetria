<?php

declare(strict_types=1);

namespace App\Modules\Billing\Data;

use Carbon\CarbonImmutable;

/** A company's plan as the console lists it: its name, what it pays a month before VAT (null while it does not pay) and when it renews. */
final readonly class CompanyRevenue
{
    public function __construct(public string $plan, public ?int $monthlyCents, public CarbonImmutable $renewsOn) {}
}
