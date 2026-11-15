<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\UsageGrant;
use Carbon\CarbonImmutable;

/** What a company may use of a meter in a month: its plan's quota in the catalog (or the default for a company without one) plus the grants of that month. */
final class UsageQuota
{
    public function for(string $tenantId, string $meter, CarbonImmutable $period): int
    {
        return $this->base($tenantId, $meter) + $this->granted($tenantId, $meter, $period);
    }

    public function base(string $tenantId, string $meter): int
    {
        $subscription = Subscription::query()->where('tenant_id', $tenantId)->first();

        if ($subscription === null) {
            return config()->integer("billing.unplanned_quotas.{$meter}");
        }

        $catalog = Catalog::current();

        // Campaign email is per seat of the plan, with a floor; the other meters are a flat amount of the plan.
        return $meter === 'email_campaign'
            ? max($catalog->quota($subscription->plan, 'email_per_seat') * $subscription->seats, $catalog->quota($subscription->plan, 'email_minimum'))
            : $catalog->quota($subscription->plan, $meter);
    }

    public function granted(string $tenantId, string $meter, CarbonImmutable $period): int
    {
        return (int) UsageGrant::query()->where('tenant_id', $tenantId)->where('meter', $meter)->whereDate('period_start', $period->toDateString())->sum('quantity');
    }
}
