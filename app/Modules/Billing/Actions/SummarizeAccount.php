<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Data\AccountSummary;
use App\Modules\Billing\Enums\Profile;
use App\Modules\Billing\Models\AccountCompany;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Support\Eligibility;
use App\Modules\Billing\Support\Money;
use App\Modules\Billing\Support\ShortDate;
use App\Modules\Billing\Support\SubscriptionQuoter;
use App\Modules\Tenancy\Contracts\CompanyRegistry;
use Carbon\CarbonImmutable;

final readonly class SummarizeAccount
{
    public function __construct(private Eligibility $eligibility, private SubscriptionQuoter $quoter, private CompanyRegistry $companies) {}

    /** What the account pays for all the companies it pays now, from their plans as they stand today, and which renews next. */
    public function handle(BillingAccount $account): AccountSummary
    {
        $companies = AccountCompany::query()->current()->where('billing_account_id', $account->id)->count();
        $subscriptions = Subscription::query()
            ->whereIn('tenant_id', AccountCompany::query()->current()->where('billing_account_id', $account->id)->select('tenant_id'))
            ->get();

        $eligible = $this->eligibility->count($account);
        $today = CarbonImmutable::now(config()->string('app.display_timezone'))->startOfDay();
        $subtotal = $vat = $total = 0;
        $priced = $requiringQuotation = 0;

        foreach ($subscriptions as $subscription) {
            $quote = $this->quoter->quote($subscription, $eligible, $today);

            if ($quote->subtotalCents === null || $quote->vatCents === null || $quote->totalCents === null) {
                $requiringQuotation++;

                continue;
            }

            $priced++;
            $subtotal += $quote->subtotalCents;
            $vat += $quote->vatCents;
            $total += $quote->totalCents;
        }

        $next = $subscriptions->sortBy(fn (Subscription $subscription): string => $subscription->renews_on->toDateString())->first();

        return new AccountSummary(
            companies: $companies,
            withPlan: $subscriptions->count(),
            subtotal: $priced === 0 ? null : Money::format($subtotal),
            vat: $priced === 0 ? null : Money::format($vat),
            total: $priced === 0 ? null : Money::format($total),
            requiringQuotation: $requiringQuotation,
            nextRenewal: $next === null ? null : ShortDate::format($next->renews_on),
            discount: $subscriptions->contains(fn (Subscription $subscription): bool => $subscription->profile === Profile::ChannelIntegral) ? $this->eligibility->explain($eligible) : null,
            nextRenewalCompany: $next === null ? null : $this->companies->find($next->tenant_id)?->tradeName,
        );
    }
}
