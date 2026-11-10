<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Data\SubscriptionData;
use App\Modules\Billing\Data\TransferContext;
use App\Modules\Billing\Data\TransferData;
use App\Modules\Billing\Enums\Profile;
use App\Modules\Billing\Exceptions\InvalidSubscription;
use App\Modules\Billing\Exceptions\InvalidTransfer;
use App\Modules\Billing\Models\AccountCompany;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Statement;
use App\Modules\Billing\Models\Subscription;

/** What billing does not allow in moving a company between payers, whether it is being done or only previewed. */
final class TransferGuard
{
    /**
     * @throws InvalidTransfer
     */
    public static function check(string $tenantId, TransferData $data): TransferContext
    {
        $link = AccountCompany::query()->current()->where('tenant_id', $tenantId)->first() ?? throw new InvalidTransfer('company', 'La empresa no tiene un pagador vigente.');
        $from = BillingAccount::query()->findOrFail($link->billing_account_id);
        $to = $data->toAccountId === null ? null : BillingAccount::query()->find($data->toAccountId) ?? throw new InvalidTransfer('to', 'Elige una cuenta de la lista.');

        if ($to?->id === $from->id) {
            throw new InvalidTransfer('to', 'Esa cuenta ya paga la empresa: elige otra.');
        }

        self::checkDate($tenantId, $link, $data);
        $subscription = Subscription::query()->where('tenant_id', $tenantId)->first();

        return new TransferContext($link, $from, $to, $subscription, self::profileAfter($to, $subscription, $data));
    }

    /** @throws InvalidTransfer */
    private static function checkDate(string $tenantId, AccountCompany $link, TransferData $data): void
    {
        if ($data->effectiveOn->day !== 1) {
            throw new InvalidTransfer('effective_on', 'La fecha efectiva es un corte: el día 1 de un mes.');
        }

        if ($data->effectiveOn->lessThan($link->starts_on)) {
            throw new InvalidTransfer('effective_on', 'La fecha efectiva no puede ser anterior a cuando la cuenta actual empezó a pagar la empresa.');
        }

        $last = Statement::query()->where('tenant_id', $tenantId)->max('period_start');

        if (is_string($last) && $data->effectiveOn->toDateString() <= $last) {
            throw new InvalidTransfer('effective_on', 'La empresa ya tiene estado de cuenta de un periodo igual o posterior a esa fecha: elige un corte posterior.');
        }
    }

    /**
     * How the plan is priced from then on: the profile chosen, or the one it has, which the new account must be able to hold.
     *
     * @throws InvalidTransfer
     */
    private static function profileAfter(?BillingAccount $to, ?Subscription $subscription, TransferData $data): ?Profile
    {
        if ($subscription === null) {
            return null;
        }

        $profile = $data->profile ?? $subscription->profile;

        if ($to === null) {
            return $profile;
        }

        try {
            SubscriptionGuard::checkAgainst($to, new SubscriptionData($subscription->plan, $profile, $subscription->status, $subscription->seats, $subscription->centers, $subscription->starts_on), $subscription);
        } catch (InvalidSubscription $exception) {
            $held = $data->profile === null && $profile->isChannel() && $exception->field === 'profile';

            throw new InvalidTransfer('profile', $held ? "La cuenta de destino no admite {$profile->label()}: elige cómo se cobrará la empresa." : $exception->getMessage());
        }

        return $profile;
    }
}
