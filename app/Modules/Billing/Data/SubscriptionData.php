<?php

declare(strict_types=1);

namespace App\Modules\Billing\Data;

use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Enums\Profile;
use App\Modules\Billing\Enums\SubscriptionStatus;
use Carbon\CarbonImmutable;

/** What the console sends to give a company its plan, or to change it. */
final readonly class SubscriptionData
{
    public function __construct(
        public Plan $plan,
        public Profile $profile,
        public SubscriptionStatus $status,
        public int $seats,
        public int $centers,
        public CarbonImmutable $startsOn,
        public ?int $negotiatedPriceCents = null,
        public ?CarbonImmutable $negotiatedValidUntil = null,
    ) {}
}
