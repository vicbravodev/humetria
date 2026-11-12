<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Data\SubscriptionData;
use App\Modules\Billing\Enums\Profile;
use App\Modules\Billing\Exceptions\InvalidSubscription;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Support\Catalog;
use App\Modules\Billing\Support\Eligibility;
use App\Modules\Billing\Support\Proration;
use App\Modules\Billing\Support\QuoteView;
use App\Modules\Billing\Support\ScheduledChange;
use App\Modules\Billing\Support\ShortDate;
use App\Modules\Billing\Support\SubscriptionGuard;
use App\Modules\Billing\Support\SubscriptionQuoter;
use Carbon\CarbonImmutable;

final readonly class PreviewSubscription
{
    public function __construct(private Eligibility $eligibility, private SubscriptionQuoter $quoter) {}

    /**
     * What the plan would cost if it were saved, next to what the company pays now, with nothing written: the next amount shown
     * before it is accepted. The company counts for the Integral discount as it is being priced.
     *
     * @return array{quote: array<string, mixed>, current: array<string, mixed>|null, first_period: array<string, mixed>|null, eligibility: array{applies: bool, reason: string}|null, scheduled: array{effective_on: string}|null}
     *
     * @throws InvalidSubscription
     */
    public function handle(string $tenantId, SubscriptionData $data): array
    {
        $existing = Subscription::query()->where('tenant_id', $tenantId)->first();
        $account = SubscriptionGuard::check($tenantId, $data, $existing);
        $today = CarbonImmutable::now(config()->string('app.display_timezone'))->startOfDay();

        $draft = new Subscription([
            'tenant_id' => $tenantId,
            'plan' => $data->plan,
            'profile' => $data->profile,
            'catalog_version' => $existing->catalog_version ?? Catalog::current()->name(),
            'status' => $data->status,
            'seats' => $data->seats,
            'centers' => $data->centers,
            'starts_on' => $data->startsOn,
            'renews_on' => $data->startsOn->startOfMonth()->addMonth(),
            'negotiated_price_cents' => $data->negotiatedPriceCents,
            'negotiated_valid_until' => $data->negotiatedValidUntil,
        ]);

        $eligible = $this->eligibility->count($account, $draft);
        $cut = CarbonImmutable::parse($today->startOfMonth()->addMonth()->toDateString());
        $split = ScheduledChange::split($existing, $data, $cut);

        return [
            'quote' => QuoteView::from($this->quoter->quote($draft, $eligible, $today)),
            'current' => $existing === null ? null : QuoteView::from($this->quoter->quote($existing, $this->eligibility->count($account), $today)),
            'first_period' => Proration::startingOn($data->startsOn) === null ? null : QuoteView::from($this->quoter->quote($draft, $eligible, $today, firstPeriod: true)),
            'eligibility' => $data->profile === Profile::ChannelIntegral ? $this->eligibility->explain($eligible) : null,
            // A reduction (smaller plan, fewer people or centers) is not applied when saved: it waits for the next cut.
            'scheduled' => $split->effectiveOn === null ? null : ['effective_on' => ShortDate::format($split->effectiveOn)],
        ];
    }
}
