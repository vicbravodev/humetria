<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Contracts\CompanyPlans;
use App\Modules\Billing\Contracts\PlanNames;
use App\Modules\Billing\Data\PlanSummary;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Subscription;

final class SubscriptionPlans implements CompanyPlans, PlanNames
{
    public function current(string $tenantId): ?PlanSummary
    {
        $subscription = $this->subscription($tenantId);

        return $subscription === null ? null : new PlanSummary($subscription->plan->label(), $subscription->starts_on, $subscription->renews_on);
    }

    public function all(): array
    {
        return array_combine(array_map(fn (Plan $plan): string => $plan->value, Plan::cases()), array_map(fn (Plan $plan): string => $plan->label(), Plan::cases()));
    }

    public function keyOf(string $tenantId): ?string
    {
        return $this->subscription($tenantId)?->plan->value;
    }

    private function subscription(string $tenantId): ?Subscription
    {
        $subscription = Subscription::query()->where('tenant_id', $tenantId)->first();

        return $subscription === null || $subscription->status === SubscriptionStatus::Ended ? null : $subscription;
    }
}
