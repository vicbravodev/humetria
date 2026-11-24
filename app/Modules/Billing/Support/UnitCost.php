<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

/** What the provider charges for one unit and what the company is charged, in millionths of the currency's unit. */
final readonly class UnitCost
{
    public function __construct(public int $costMicros, public int $priceMicros, public string $currency) {}
}
