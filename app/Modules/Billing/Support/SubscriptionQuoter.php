<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Data\QuoteResult;
use App\Modules\Billing\Models\Subscription;
use Carbon\CarbonImmutable;

/** Quotes a subscription as it stands on a day: its own catalog, its negotiated price if one is in force then. */
final class SubscriptionQuoter
{
    /**
     * @param  int  $eligibleIntegralCompanies  from Eligibility, for the account that pays it.
     * @param  bool  $firstPeriod  the first, partial month of a subscription that starts after the 1st.
     */
    public function quote(Subscription $subscription, int $eligibleIntegralCompanies, CarbonImmutable $on, bool $firstPeriod = false): QuoteResult
    {
        return Quote::for(
            $subscription->plan,
            $subscription->profile,
            $subscription->seats,
            $subscription->centers,
            $subscription->catalog_version,
            $eligibleIntegralCompanies,
            $subscription->negotiatedPriceOn($on),
            $firstPeriod ? Proration::startingOn($subscription->starts_on) : null,
        );
    }
}
