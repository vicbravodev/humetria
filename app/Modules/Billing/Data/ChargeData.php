<?php

declare(strict_types=1);

namespace App\Modules\Billing\Data;

use App\Modules\Billing\Enums\ChargeKind;
use Carbon\CarbonImmutable;

/** What the console sends to add an extra charge to a company. */
final readonly class ChargeData
{
    public function __construct(
        public ChargeKind $kind,
        public string $description,
        public string $scope,
        public int $amountCents,
        public CarbonImmutable $chargeOn,
        public ?CarbonImmutable $endsOn = null,
    ) {}
}
