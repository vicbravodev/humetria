<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Models\UsageCounter;
use App\Modules\Billing\Models\UsageReservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class ReleaseUsage
{
    /**
     * Gives back the whole hold of a call that failed (or, as `$state`, of one that expired). A key that is not held does nothing.
     *
     * @return bool whether a hold was released.
     */
    public function handle(string $tenantId, string $key, string $state = UsageReservation::RELEASED): bool
    {
        return DB::transaction(function () use ($tenantId, $key, $state): bool {
            $reservation = UsageReservation::query()->where('tenant_id', $tenantId)->where('idempotency_key', $key)->lockForUpdate()->first();

            if ($reservation === null || $reservation->state !== UsageReservation::RESERVED) {
                return false;
            }

            UsageCounter::query()->where('tenant_id', $tenantId)->where('meter', $reservation->meter)->whereDate('period_start', $reservation->period_start->toDateString())
                ->decrement('reserved', $reservation->quantity);
            $reservation->update(['state' => $state]);

            Log::info("usage.{$state}", ['tenant_id' => $tenantId, 'meter' => $reservation->meter, 'quantity' => $reservation->quantity]);

            return true;
        });
    }
}
