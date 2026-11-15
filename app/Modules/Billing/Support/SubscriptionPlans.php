<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Contracts\CompanyPlans;
use App\Modules\Billing\Data\PlanSummary;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Subscription;

final class SubscriptionPlans implements CompanyPlans
{
    public function current(string $tenantId): ?PlanSummary
    {
        $subscription = Subscription::query()->where('tenant_id', $tenantId)->first();

        if ($subscription === null || $subscription->status === SubscriptionStatus::Ended) {
            return null;
        }

        return new PlanSummary($subscription->plan->label(), $subscription->starts_on, $subscription->renews_on);
    }
}
