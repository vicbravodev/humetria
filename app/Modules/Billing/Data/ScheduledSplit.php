<?php

declare(strict_types=1);

namespace App\Modules\Billing\Data;

use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Enums\Profile;
use Carbon\CarbonImmutable;

/**
 * What a change to a plan does: the plan, profile, people and centers that apply now, and what waits for the next cut (a null
 * pending is a field that does not change).
 */
final readonly class ScheduledSplit
{
    public function __construct(
        public Plan $plan,
        public Profile $profile,
        public int $seats,
        public int $centers,
        public ?Plan $pendingPlan,
        public ?Profile $pendingProfile,
        public ?int $pendingSeats,
        public ?int $pendingCenters,
        public ?CarbonImmutable $effectiveOn,
    ) {}

    public function hasPending(): bool
    {
        return $this->effectiveOn !== null;
    }
}
