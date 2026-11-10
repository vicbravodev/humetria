<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Actions\RecordAudit;
use App\Modules\Billing\Data\SubscriptionData;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Exceptions\InvalidSubscription;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Support\Catalog;
use App\Modules\Billing\Support\SubscriptionGuard;
use App\Modules\Tenancy\Contracts\CompanyLimits;
use App\Modules\Tenancy\Contracts\CompanyModules;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class SetSubscription
{
    public function __construct(private RecordAudit $recordAudit, private CompanyModules $modules, private CompanyLimits $limits) {}

    /**
     * Gives the company its plan, or changes the one it has: the plan, how it is priced, the people and centers it pays for.
     * A new one is quoted with the current catalog; a change keeps the catalog it was quoted with. It never touches the
     * company's dates or restriction. Its modules follow the plan through Tenancy's contract: a trial has them all, a plan only
     * its own, from the current catalog (what a plan includes is not price-locked); what a plan adds is on at once and what it takes away ends the day the plan starts (never earlier than today).
     *
     * @throws InvalidSubscription
     */
    public function handle(string $tenantId, SubscriptionData $data): Subscription
    {
        SubscriptionGuard::check($tenantId, $data, Subscription::query()->where('tenant_id', $tenantId)->first());

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

            $this->modules->syncPlan(
                $tenantId,
                $data->status === SubscriptionStatus::Trial ? Catalog::current()->allModules() : Catalog::current()->modules($data->plan),
                $data->startsOn->greaterThan($today = CarbonImmutable::now(config()->string('app.display_timezone'))->startOfDay()) ? $data->startsOn : $today,
            );

            // What the plan contracts is what the company may have: its people, and its centers (never fewer than the plan includes).
            $this->limits->sync($tenantId, [
                CompanyLimits::SEATS => $data->seats,
                CompanyLimits::CENTERS => max($data->centers, Catalog::current()->includedCenters($data->plan)),
            ]);

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
}
