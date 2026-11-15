<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Contracts\PlatformRevenue;
use App\Modules\Billing\Enums\Profile;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\AccountCompany;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Subscription;
use Carbon\CarbonImmutable;

/**
 * The monthly income the subscriptions that pay now contract, quoted as the account pages quote them, one by one. Only a
 * channel account's discount depends on how many of its companies pay Integral, so only those accounts are asked.
 */
final readonly class ContractedRevenue implements PlatformRevenue
{
    public function __construct(private Eligibility $eligibility, private SubscriptionQuoter $quoter) {}

    public function monthlyContractedCents(): int
    {
        $paying = array_map(fn (SubscriptionStatus $status): string => $status->value, array_filter(SubscriptionStatus::cases(), fn (SubscriptionStatus $status): bool => $status->isPaying()));
        $subscriptions = Subscription::query()->whereIn('status', $paying)->get();
        $channel = [];

        foreach ($subscriptions as $subscription) {
            if ($subscription->profile === Profile::ChannelIntegral) {
                $channel[] = $subscription->tenant_id;
            }
        }

        $eligible = $this->eligibleByCompany($channel);
        $today = CarbonImmutable::now(config()->string('app.display_timezone'))->startOfDay();
        $total = 0;

        foreach ($subscriptions as $subscription) {
            // A price that needs a quotation has no figure yet.
            $total += $this->quoter->quote($subscription, $eligible[$subscription->tenant_id] ?? 0, $today)->subtotalCents ?? 0;
        }

        return $total;
    }

    /**
     * @param  list<string>  $tenantIds
     * @return array<string, int> the eligible Integral companies of the account that pays each company.
     */
    private function eligibleByCompany(array $tenantIds): array
    {
        if ($tenantIds === []) {
            return [];
        }

        $links = AccountCompany::query()->current()->whereIn('tenant_id', $tenantIds)->get(['tenant_id', 'billing_account_id']);
        $counts = [];

        foreach (BillingAccount::query()->whereIn('id', $links->pluck('billing_account_id')->unique()->values())->get() as $account) {
            $counts[$account->id] = $this->eligibility->count($account);
        }

        $byCompany = [];

        foreach ($links as $link) {
            $byCompany[$link->tenant_id] = $counts[$link->billing_account_id] ?? 0;
        }

        return $byCompany;
    }
}
