<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Actions\RecordAudit;
use App\Modules\Billing\Enums\ChargeKind;
use App\Modules\Billing\Exceptions\InvalidCharge;
use App\Modules\Billing\Models\SubscriptionCharge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class RemoveCharge
{
    public function __construct(private RecordAudit $recordAudit) {}

    /**
     * Deletes a one-time charge no statement has billed. A billed one stays (it is on a statement); a recurring one is ended,
     * not removed. The audit entry keeps what it was and why it went.
     *
     * @throws InvalidCharge
     */
    public function handle(SubscriptionCharge $charge, string $reason): void
    {
        DB::transaction(function () use ($charge, $reason): void {
            $locked = SubscriptionCharge::query()->whereKey($charge->id)->lockForUpdate()->firstOrFail();

            if ($locked->kind !== ChargeKind::OneTime) {
                throw new InvalidCharge('charge', 'Un cargo recurrente no se quita: ponle su último día.');
            }

            if ($locked->billed_at !== null) {
                throw new InvalidCharge('charge', 'Este cargo ya se facturó y queda en su estado de cuenta; ajusta ese estado si hace falta.');
            }

            $locked->delete();

            Log::info('billing.charge_removed', ['tenant_id' => $locked->tenant_id, 'kind' => $locked->kind->value]);
            $this->recordAudit->handle('billing.charge_removed', $locked, ['tenant_id' => $locked->tenant_id, 'kind' => $locked->kind->value, 'amount_cents' => $locked->amount_cents, 'reason' => $reason]);
        });
    }
}
