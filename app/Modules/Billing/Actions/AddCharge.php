<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Actions\RecordAudit;
use App\Models\User;
use App\Modules\Billing\Data\ChargeData;
use App\Modules\Billing\Exceptions\CompanyHasNoPlan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\SubscriptionCharge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class AddCharge
{
    public function __construct(private RecordAudit $recordAudit) {}

    /**
     * Adds an extra charge to the company's plan: recurring (the accompaniment) or once (an implementation, a study), approved by
     * whoever adds it. It changes nothing else: statements will pick it up when they are made.
     *
     * @throws CompanyHasNoPlan
     */
    public function handle(string $tenantId, ChargeData $data, User $approver): SubscriptionCharge
    {
        $subscription = Subscription::query()->where('tenant_id', $tenantId)->first() ?? throw CompanyHasNoPlan::forCharge();

        return DB::transaction(function () use ($subscription, $tenantId, $data, $approver): SubscriptionCharge {
            $charge = SubscriptionCharge::query()->create([
                'subscription_id' => $subscription->id,
                'tenant_id' => $tenantId,
                'kind' => $data->kind,
                'description' => $data->description,
                'scope' => $data->scope,
                'amount_cents' => $data->amountCents,
                'charge_on' => $data->chargeOn,
                'ends_on' => $data->endsOn,
                'approved_by' => $approver->id,
            ]);

            Log::info('billing.charge_added', ['tenant_id' => $tenantId, 'kind' => $data->kind->value]);
            $this->recordAudit->handle('billing.charge_added', $charge, [
                'tenant_id' => $tenantId,
                'kind' => $data->kind->value,
                'amount_cents' => $data->amountCents,
                'charge_on' => $data->chargeOn->toDateString(),
            ]);

            return $charge;
        });
    }
}
