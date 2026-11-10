<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Actions\RecordAudit;
use App\Modules\Billing\Enums\ChargeKind;
use App\Modules\Billing\Exceptions\InvalidCharge;
use App\Modules\Billing\Models\SubscriptionCharge;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class EndCharge
{
    public function __construct(private RecordAudit $recordAudit) {}

    /**
     * Gives a recurring charge its last day: statements bill it up to the month that day falls in and no further. A charge
     * already billed stays billed; one that already ends has its day, and one that is paid once has nothing to end.
     *
     * @throws InvalidCharge
     */
    public function handle(SubscriptionCharge $charge, CarbonImmutable $endsOn, string $reason): void
    {
        DB::transaction(function () use ($charge, $endsOn, $reason): void {
            $locked = SubscriptionCharge::query()->whereKey($charge->id)->lockForUpdate()->firstOrFail();

            if ($locked->kind !== ChargeKind::Recurring) {
                throw new InvalidCharge('kind', 'Un cargo de una sola vez no termina: si no se ha facturado, quítalo.');
            }

            if ($locked->ends_on !== null) {
                throw new InvalidCharge('ends_on', 'Este cargo ya tiene un último día.');
            }

            if ($endsOn->lessThan($locked->charge_on)) {
                throw new InvalidCharge('ends_on', 'El último día no puede ser antes del inicio del cargo.');
            }

            $locked->update(['ends_on' => $endsOn]);

            Log::info('billing.charge_ended', ['tenant_id' => $locked->tenant_id, 'ends_on' => $endsOn->toDateString()]);
            $this->recordAudit->handle('billing.charge_ended', $locked, ['tenant_id' => $locked->tenant_id, 'ends_on' => $endsOn->toDateString(), 'reason' => $reason]);
        });
    }
}
