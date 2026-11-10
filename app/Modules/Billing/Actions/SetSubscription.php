<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Actions\RecordAudit;
use App\Modules\Billing\Data\SubscriptionData;
use App\Modules\Billing\Enums\AccountKind;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Exceptions\InvalidSubscription;
use App\Modules\Billing\Models\AccountCompany;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Support\Catalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class SetSubscription
{
    public function __construct(private RecordAudit $recordAudit) {}

    /**
     * Gives the company its plan, or changes the one it has: the plan, how it is priced, the people and centers it pays for.
     * A new one is quoted with the current catalog; a change keeps the catalog it was quoted with. It never touches the
     * company's access: dates, restriction and modules are Tenancy's, and move on their own.
     *
     * @throws InvalidSubscription
     */
    public function handle(string $tenantId, SubscriptionData $data): Subscription
    {
        $this->guard($tenantId, $data, Subscription::query()->where('tenant_id', $tenantId)->first());

        return DB::transaction(function () use ($tenantId, $data): Subscription {
            $subscription = Subscription::query()->where('tenant_id', $tenantId)->lockForUpdate()->first();
            $created = $subscription === null;
            $subscription ??= new Subscription(['tenant_id' => $tenantId, 'catalog_version' => Catalog::current()->name()]);

            $startMoved = $created || ! $subscription->starts_on->equalTo($data->startsOn);

            $subscription->fill([
                'plan' => $data->plan,
                'profile' => $data->profile,
                'status' => $data->status,
                'seats' => $data->seats,
                'centers' => $data->centers,
                'starts_on' => $data->startsOn,
                'negotiated_price_cents' => $data->negotiatedPriceCents,
                'negotiated_valid_until' => $data->negotiatedValidUntil,
            ]);

            if ($startMoved) {
                // The cut is the 1st: a plan renews on the first day of the month after its start.
                $subscription->renews_on = $data->startsOn->startOfMonth()->addMonth();
            }

            $subscription->save();

            Log::info('billing.subscription_set', ['tenant_id' => $tenantId, 'created' => $created]);
            $this->recordAudit->handle('billing.subscription_set', $subscription, [
                'tenant_id' => $tenantId,
                'created' => $created,
                'plan' => $data->plan->value,
                'profile' => $data->profile->value,
                'status' => $data->status->value,
                'seats' => $data->seats,
                'centers' => $data->centers,
                'catalog_version' => $subscription->catalog_version,
                'negotiated_price_cents' => $data->negotiatedPriceCents,
            ]);

            return $subscription;
        });
    }

    /**
     * @throws InvalidSubscription
     */
    private function guard(string $tenantId, SubscriptionData $data, ?Subscription $current): void
    {
        $link = AccountCompany::query()->current()->where('tenant_id', $tenantId)->first();

        if ($link === null) {
            throw new InvalidSubscription('plan', 'Vincula la empresa a una cuenta antes de asignarle un plan.');
        }

        $required = $data->profile->requiredPlan();

        if ($required !== null && $required !== $data->plan) {
            throw new InvalidSubscription('profile', "{$data->profile->label()} solo va con el plan {$required->label()}.");
        }

        if ($data->profile->isChannel() && BillingAccount::query()->findOrFail($link->billing_account_id)->kind !== AccountKind::Consultant) {
            throw new InvalidSubscription('profile', 'Las tarifas de canal son solo para cuentas de consultor.');
        }

        // "Zero people does not activate a plan; one already active pays its minimum."
        if ($data->status === SubscriptionStatus::Active && $current?->status !== SubscriptionStatus::Active && $data->seats < 1) {
            throw new InvalidSubscription('seats', 'Para activar un plan hace falta al menos una persona.');
        }
    }
}
