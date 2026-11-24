<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Contracts\UsageMeter;
use App\Modules\Billing\Data\StatementDraft;
use App\Modules\Billing\Data\StatementLineData;
use App\Modules\Billing\Enums\ChargeKind;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Exceptions\InvalidStatement;
use App\Modules\Billing\Models\AccountCompany;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\SubscriptionCharge;
use App\Modules\Billing\Models\UsageEvent;
use App\Modules\Billing\Support\Catalog;
use App\Modules\Billing\Support\Eligibility;
use App\Modules\Billing\Support\Money;
use App\Modules\Billing\Support\Proration;
use App\Modules\Billing\Support\Quote;
use App\Modules\Billing\Support\ShortDate;
use App\Modules\Billing\Support\SubscriptionQuoter;
use Carbon\CarbonImmutable;

/**
 * Works out what a company owes for a calendar month, writing nothing: the amounts come only from the quote of its
 * subscription, then its extra charges, then the VAT over the subtotal, rounded once.
 */
final readonly class BuildStatement
{
    public function __construct(private Eligibility $eligibility, private SubscriptionQuoter $quoter) {}

    /**
     * @param  CarbonImmutable  $month  any day of the month.
     *
     * @throws InvalidStatement
     */
    public function handle(string $tenantId, CarbonImmutable $month): StatementDraft
    {
        $start = $month->startOfMonth()->startOfDay();
        $end = $start->addMonth();

        $subscription = Subscription::query()->where('tenant_id', $tenantId)->first() ?? throw new InvalidStatement('company', 'Asigna un plan a la empresa antes de hacerle un estado de cuenta.');
        $link = AccountCompany::query()->covering($start, $end)->where('tenant_id', $tenantId)->orderByDesc('starts_on')->first() ?? throw new InvalidStatement('company', 'Vincula la empresa a una cuenta antes de hacerle un estado de cuenta.');
        $this->guard($subscription, $end);

        $account = BillingAccount::query()->findOrFail($link->billing_account_id);
        $eligible = $this->eligibility->count($account, periodStart: $start);
        // The first, partial month of a plan that starts after the 1st: the only one that is prorated.
        $proration = $subscription->starts_on->greaterThanOrEqualTo($start) ? Proration::startingOn($subscription->starts_on) : null;
        $quote = $this->quoter->quote($subscription, $eligible, $start, $proration !== null);

        if ($quote->requiresQuotation || $quote->subtotalCents === null) {
            throw new InvalidStatement('company', 'Esta suscripción requiere cotización: fija un precio negociado antes de hacer el estado de cuenta.');
        }

        $lines = [
            ...array_map(fn ($line): StatementLineData => new StatementLineData($line->code, $line->label, $line->amountCents, $line->explanation), $quote->lines),
            ...$this->chargeLines($subscription, $start, $end),
            ...$this->whatsAppLines($tenantId, $start, $quote->currency),
        ];

        $subtotal = array_sum(array_map(fn (StatementLineData $line): int => $line->amountCents, $lines));
        $percent = Catalog::version($quote->catalogVersion)->vatPercent();
        $vat = Quote::divide($subtotal * $percent, 100);
        $lines[] = new StatementLineData('vat', "IVA {$percent}%", $vat, "{$percent}% sobre el subtotal de ".Money::format($subtotal));

        return new StatementDraft(
            $tenantId,
            $account->id,
            $start,
            $end,
            $quote->currency,
            $quote->catalogVersion,
            $percent,
            $lines,
            $subtotal,
            $vat,
            $subtotal + $vat,
            [
                'subscription_id' => $subscription->id,
                'plan' => $subscription->plan->value,
                'profile' => $subscription->profile->value,
                'seats' => $subscription->seats,
                'centers' => $subscription->centers,
                'eligible_integral_companies' => $eligible,
                'negotiated_price_cents' => $subscription->negotiatedPriceOn($start),
                'proration' => $proration === null ? null : ['days_remaining' => $proration->daysRemaining, 'period_days' => $proration->periodDays],
            ],
        );
    }

    /**
     * The WhatsApp messages the company was charged this month, as one line at the price stored on each usage line.
     *
     * @return list<StatementLineData>
     *
     * @throws InvalidStatement when a message was priced in another currency than the statement.
     */
    private function whatsAppLines(string $tenantId, CarbonImmutable $start, string $currency): array
    {
        $totals = UsageEvent::query()->toBase()
            ->where('tenant_id', $tenantId)
            ->where('meter', UsageMeter::WHATSAPP)
            ->whereDate('period_start', $start->toDateString())
            ->selectRaw('count(*) as messages, coalesce(sum(unit_price_micros), 0) as price, min(cost_currency) as first_currency, max(cost_currency) as last_currency')
            ->first();

        $messages = is_numeric($totals?->messages) ? (int) $totals->messages : 0;

        if ($totals === null || $messages === 0) {
            return [];
        }

        if ($totals->first_currency !== $currency || $totals->last_currency !== $currency) {
            throw new InvalidStatement('currency', 'Los mensajes de WhatsApp del mes no están en la moneda del estado de cuenta.');
        }

        $price = is_numeric($totals->price) ? (int) $totals->price : 0;

        return [new StatementLineData('whatsapp', 'Mensajes de WhatsApp', (int) round($price / 10_000), "{$messages} mensajes")];
    }

    /** @throws InvalidStatement */
    private function guard(Subscription $subscription, CarbonImmutable $end): void
    {
        if ($subscription->status === SubscriptionStatus::Trial) {
            throw new InvalidStatement('company', 'Una suscripción en prueba no se factura.');
        }

        if ($subscription->status === SubscriptionStatus::Ended) {
            throw new InvalidStatement('company', 'Una suscripción terminada ya no se factura.');
        }

        if ($subscription->starts_on->greaterThanOrEqualTo($end)) {
            throw new InvalidStatement('period', 'La suscripción empieza después de ese periodo.');
        }
    }

    /**
     * The recurring charges in force during the month, and the one-time charges that fall in it and nobody billed yet.
     *
     * @return list<StatementLineData>
     */
    private function chargeLines(Subscription $subscription, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $charges = SubscriptionCharge::query()
            ->where('subscription_id', $subscription->id)
            ->where('charge_on', '<', $end->toDateString())
            ->where(fn ($query) => $query
                ->where(fn ($recurring) => $recurring->where('kind', ChargeKind::Recurring)->where(fn ($ends) => $ends->whereNull('ends_on')->orWhere('ends_on', '>=', $start->toDateString())))
                ->orWhere(fn ($once) => $once->where('kind', ChargeKind::OneTime)->where('charge_on', '>=', $start->toDateString())->whereNull('billed_at')))
            ->orderBy('kind', 'desc')
            ->orderBy('charge_on')
            ->orderBy('id')
            ->get();

        return array_values($charges->map(fn (SubscriptionCharge $charge): StatementLineData => $charge->kind === ChargeKind::Recurring
            ? new StatementLineData('charge_recurring', $charge->description, $charge->amount_cents, 'Cargo recurrente desde '.ShortDate::format($charge->charge_on).": {$charge->scope}", $charge->id)
            : new StatementLineData('charge_one_time', $charge->description, $charge->amount_cents, 'Cargo de una sola vez del '.ShortDate::format($charge->charge_on).": {$charge->scope}", $charge->id),
        )->all());
    }
}
