<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Data\ScheduledSplit;
use App\Modules\Billing\Data\SubscriptionData;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Subscription;
use Carbon\CarbonImmutable;

/**
 * The rule of reductions (spec §8, decision 6): what a company pays more for applies at once; a smaller plan, fewer people or
 * fewer centers wait for the next cut, with no credit. A company with no plan or still in trial has nobody billed, so
 * everything applies at once. The wish is always the whole plan: asking again for what the company has cancels what waited.
 */
final class ScheduledChange
{
    public static function split(?Subscription $current, SubscriptionData $wanted, CarbonImmutable $cut): ScheduledSplit
    {
        if ($current === null || $current->status === SubscriptionStatus::Trial) {
            return new ScheduledSplit($wanted->plan, $wanted->profile, $wanted->seats, $wanted->centers, null, null, null, null, null);
        }

        // Which plan is bigger is read from the current catalog: a row quoted with an older one still compares like the rest.
        $catalog = Catalog::current();
        $smallerPlan = $catalog->rateCents($wanted->plan) < $catalog->rateCents($current->plan);
        $fewerSeats = $wanted->seats < $current->seats;
        $fewerCenters = $wanted->centers < $current->centers;

        return new ScheduledSplit(
            $smallerPlan ? $current->plan : $wanted->plan,
            $smallerPlan ? $current->profile : $wanted->profile,
            $fewerSeats ? $current->seats : $wanted->seats,
            $fewerCenters ? $current->centers : $wanted->centers,
            $smallerPlan ? $wanted->plan : null,
            $smallerPlan ? $wanted->profile : null,
            $fewerSeats ? $wanted->seats : null,
            $fewerCenters ? $wanted->centers : null,
            $smallerPlan || $fewerSeats || $fewerCenters ? $cut : null,
        );
    }
}
