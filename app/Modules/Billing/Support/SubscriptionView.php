<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Data\QuoteResult;
use App\Modules\Billing\Models\Subscription;

/** What a company contracted, as the screen and its form read it. */
final class SubscriptionView
{
    /**
     * @return array<string, mixed>
     */
    public static function from(Subscription $subscription, QuoteResult $quote): array
    {
        return [
            'plan' => $subscription->plan->value,
            'plan_label' => $subscription->plan->label(),
            'profile' => $subscription->profile->value,
            'profile_label' => $subscription->profile->label(),
            'status' => $subscription->status->value,
            'status_label' => $subscription->status->label(),
            'seats' => $subscription->seats,
            'centers' => $subscription->centers,
            'allowed_centers' => $subscription->allowedCenters(),
            'starts_on' => $subscription->starts_on->toDateString(),
            'renews_on' => ShortDate::format($subscription->renews_on),
            'catalog_version' => $subscription->catalog_version,
            'negotiated_price' => $subscription->negotiated_price_cents === null ? '' : Money::input($subscription->negotiated_price_cents),
            'negotiated_valid_until' => $subscription->negotiated_valid_until?->toDateString() ?? '',
            'quote' => QuoteView::from($quote),
            'scheduled' => $subscription->pending_effective_on === null ? null : [
                'effective_on' => ShortDate::format($subscription->pending_effective_on),
                'summary' => self::pendingSummary($subscription),
            ],
        ];
    }

    /** "Plan Jornada · 30 personas · 1 centro": only what changes at the cut. */
    private static function pendingSummary(Subscription $subscription): string
    {
        return implode(' · ', array_filter([
            $subscription->pending_plan === null ? null : "Plan {$subscription->pending_plan->label()}",
            $subscription->pending_seats === null ? null : $subscription->pending_seats.($subscription->pending_seats === 1 ? ' persona' : ' personas'),
            $subscription->pending_centers === null ? null : $subscription->pending_centers.($subscription->pending_centers === 1 ? ' centro' : ' centros'),
        ]));
    }
}
