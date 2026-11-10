<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\AccountCompany;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Tenancy\Contracts\CompanyRegistry;

/**
 * How many of an account's companies pay Integral now, which is what a consultant's discount asks for: companies it pays
 * today whose plan is Integral and paid (not a trial, not past due, not ended), and that nobody suspended or archived.
 */
final readonly class Eligibility
{
    public function __construct(private CompanyRegistry $companies) {}

    /**
     * @param  Subscription|null  $simulated  a plan not saved yet: it counts instead of the one the company has.
     */
    public function count(BillingAccount $account, ?Subscription $simulated = null): int
    {
        $payingStatuses = array_map(
            fn (SubscriptionStatus $status): string => $status->value,
            array_filter(SubscriptionStatus::cases(), fn (SubscriptionStatus $status): bool => $status->isPaying()),
        );

        $others = Subscription::query()
            ->whereIn('tenant_id', AccountCompany::query()->current()->where('billing_account_id', $account->id)->select('tenant_id'))
            ->when($simulated !== null, fn ($query) => $query->where('tenant_id', '<>', $simulated?->tenant_id))
            ->where('plan', Plan::Integral)
            ->whereIn('status', $payingStatuses)
            ->pluck('tenant_id')
            ->all();

        $counted = array_values(array_filter($others, 'is_string'));

        if ($simulated !== null && $simulated->plan === Plan::Integral && $simulated->status->isPaying()) {
            $counted[] = $simulated->tenant_id;
        }

        return count(array_filter($this->companies->many($counted), fn ($brief): bool => ! $brief->withdrawn));
    }

    /**
     * Why the discount applies or not, for the account's page; null when there is nothing to explain.
     *
     * @return array{applies: bool, reason: string}
     */
    public function explain(int $eligible): array
    {
        $catalog = Catalog::current();
        $minimum = $catalog->integralMinimumCompanies();
        $percent = $catalog->integralDiscountPercent();

        if ($eligible >= $minimum) {
            return ['applies' => true, 'reason' => "Descuento de {$percent}% aplicado: {$eligible} empresas con Integral pagado (el canal pide {$minimum})."];
        }

        $missing = $minimum - $eligible;

        return ['applies' => false, 'reason' => "Sin descuento: {$eligible} de las {$minimum} empresas con Integral pagado que pide el canal. Pagan la tarifa directa; con {$missing} más, el descuento es de {$percent}%."];
    }
}
