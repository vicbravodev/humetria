<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Contracts\PlanSteps;
use App\Modules\Billing\Models\AccountCompany;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Subscription;
use Carbon\CarbonImmutable;

final readonly class SubscriptionSteps implements PlanSteps
{
    public function __construct(private Eligibility $eligibility, private SubscriptionQuoter $quoter) {}

    public function describe(string $tenantId, string $resource, int $target): ?string
    {
        $subscription = Subscription::query()->where('tenant_id', $tenantId)->first();
        $link = AccountCompany::query()->current()->where('tenant_id', $tenantId)->first();
        $today = CarbonImmutable::now(config()->string('app.display_timezone'))->startOfDay();

        // A negotiated price is the same whatever the size: Humetria says what the bigger one costs.
        if ($subscription === null || $link === null || $subscription->negotiatedPriceOn($today) !== null) {
            return null;
        }

        $eligible = $this->eligibility->count(BillingAccount::query()->findOrFail($link->billing_account_id));
        $next = $subscription->replicate();
        $seats = $resource === 'seats';
        $next->{$seats ? 'seats' : 'centers'} = $target;
        $size = $seats ? "{$target} personas" : "{$target} ".($target === 1 ? 'centro de trabajo' : 'centros de trabajo');

        $now = $this->quoter->quote($subscription, $eligible, $today);
        $then = $this->quoter->quote($next, $eligible, $today);

        return match (true) {
            $then->requiresQuotation && ! $now->requiresQuotation => "Con {$size} tu plan requiere una cotización.",
            $now->subtotalCents === null || $then->subtotalCents === null || $now->subtotalCents === $then->subtotalCents => null,
            default => "Con {$size} el importe pasa de ".Money::format($now->subtotalCents).' a '.Money::format($then->subtotalCents).' al mes, sin IVA.',
        };
    }
}
