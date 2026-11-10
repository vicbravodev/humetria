<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Data\SubscriptionData;
use App\Modules\Billing\Enums\AccountKind;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Exceptions\InvalidSubscription;
use App\Modules\Billing\Models\AccountCompany;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Subscription;

/** What billing does not allow in a plan, whether it is being saved or only priced: the same rules for both. */
final class SubscriptionGuard
{
    /**
     * @param  Subscription|null  $current  the plan the company has now, if any.
     * @return BillingAccount the account that pays the company.
     *
     * @throws InvalidSubscription
     */
    public static function check(string $tenantId, SubscriptionData $data, ?Subscription $current): BillingAccount
    {
        $link = AccountCompany::query()->current()->where('tenant_id', $tenantId)->first();

        if ($link === null) {
            throw new InvalidSubscription('plan', 'Vincula la empresa a una cuenta antes de asignarle un plan.');
        }

        $account = BillingAccount::query()->findOrFail($link->billing_account_id);
        $required = $data->profile->requiredPlan();

        if ($required !== null && $required !== $data->plan) {
            throw new InvalidSubscription('profile', "{$data->profile->label()} solo va con el plan {$required->label()}.");
        }

        if ($data->profile->isChannel() && $account->kind !== AccountKind::Consultant) {
            throw new InvalidSubscription('profile', 'Las tarifas de canal son solo para cuentas de consultor.');
        }

        // "Zero people does not activate a plan; one already active pays its minimum."
        if ($data->status === SubscriptionStatus::Active && $current?->status !== SubscriptionStatus::Active && $data->seats < 1) {
            throw new InvalidSubscription('seats', 'Para activar un plan hace falta al menos una persona.');
        }

        return $account;
    }
}
