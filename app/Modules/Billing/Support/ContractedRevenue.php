<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Contracts\PlatformRevenue;
use App\Modules\Billing\Data\CompanyRevenue;
use App\Modules\Billing\Enums\Profile;
use App\Modules\Billing\Enums\StatementStatus;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\AccountCompany;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Statement;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Tenancy\Contracts\DemoCompanies;
use Carbon\CarbonImmutable;

/**
 * The monthly income the subscriptions that pay now contract, quoted as the account pages quote them, one by one. Only a
 * channel account's discount depends on how many of its companies pay Integral, so only those accounts are asked.
 */
final readonly class ContractedRevenue implements PlatformRevenue
{
    public function __construct(private Eligibility $eligibility, private SubscriptionQuoter $quoter, private DemoCompanies $demos) {}

    public function monthlyContractedCents(): int
    {
        $paying = array_map(fn (SubscriptionStatus $status): string => $status->value, array_filter(SubscriptionStatus::cases(), fn (SubscriptionStatus $status): bool => $status->isPaying()));
        // The demo company pays nothing, whatever it was given.
        $subscriptions = Subscription::query()->whereIn('status', $paying)
            ->when($this->demos->id(), fn ($query, string $demo) => $query->where('tenant_id', '<>', $demo))
            ->get();

        return array_sum($this->monthly(array_values($subscriptions->all())));
    }

    public function byCompany(array $tenantIds): array
    {
        if ($tenantIds === []) {
            return [];
        }

        $subscriptions = Subscription::query()->whereIn('tenant_id', $tenantIds)
            ->where('status', '<>', SubscriptionStatus::Ended->value)
            ->get()
            ->all();
        $monthly = $this->monthly(array_values(array_filter($subscriptions, fn (Subscription $subscription): bool => $subscription->status->isPaying())));
        $rows = [];

        foreach ($subscriptions as $subscription) {
            $rows[$subscription->tenant_id] = new CompanyRevenue($subscription->plan->label(), $monthly[$subscription->tenant_id] ?? null, $subscription->renews_on);
        }

        return $rows;
    }

    public function billedByMonth(int $months): array
    {
        $current = CarbonImmutable::now(config()->string('app.display_timezone'))->startOfMonth();
        $first = $current->subMonths($months - 1);
        $billed = Statement::query()
            ->where('status', StatementStatus::Issued)
            ->whereBetween('period_start', [$first->toDateString(), $current->endOfMonth()->toDateString()])
            ->get(['period_start', 'subtotal_cents'])
            ->groupBy(fn (Statement $statement): string => $statement->period_start->format('Y-m'))
            ->map(fn ($statements): int => $statements->sum(fn (Statement $statement): int => $statement->subtotal_cents));

        return array_map(function (int $offset) use ($first, $billed): array {
            $month = $first->addMonths($offset)->format('Y-m');

            return ['month' => $month, 'cents' => $billed->get($month, 0)];
        }, range(0, $months - 1));
    }

    /**
     * What each paying subscription costs a month before VAT, quoted as the account pages quote it; a price that needs a
     * quotation has no figure yet.
     *
     * @param  list<Subscription>  $subscriptions
     * @return array<string, int> by company id
     */
    private function monthly(array $subscriptions): array
    {
        $channel = [];

        foreach ($subscriptions as $subscription) {
            if ($subscription->profile === Profile::ChannelIntegral) {
                $channel[] = $subscription->tenant_id;
            }
        }

        $eligible = $this->eligibleByCompany($channel);
        $today = CarbonImmutable::now(config()->string('app.display_timezone'))->startOfDay();
        $cents = [];

        foreach ($subscriptions as $subscription) {
            $cents[$subscription->tenant_id] = $this->quoter->quote($subscription, $eligible[$subscription->tenant_id] ?? 0, $today)->subtotalCents ?? 0;
        }

        return $cents;
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
