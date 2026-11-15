<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Models\UsageCounter;
use App\Modules\Billing\Models\UsageEvent;
use App\Modules\Billing\Models\UsageReservation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final readonly class SettleUsage
{
    /**
     * Counts what the call really used in the month it was reserved in, records the event once, and returns the whole hold. The real
     * figure counts even when it is above the hold. A key that is not held (unknown, settled, released) does nothing.
     */
    public function handle(string $tenantId, string $key, int $actual): void
    {
        DB::transaction(function () use ($tenantId, $key, $actual): void {
            $reservation = UsageReservation::query()->where('tenant_id', $tenantId)->where('idempotency_key', $key)->lockForUpdate()->first();

            if ($reservation === null || $reservation->state !== UsageReservation::RESERVED) {
                return;
            }

            $now = CarbonImmutable::now();
            UsageEvent::query()->insertOrIgnore([
                'id' => Str::uuid()->toString(),
                'tenant_id' => $tenantId,
                'meter' => $reservation->meter,
                'period_start' => $reservation->period_start->toDateString(),
                'quantity' => $actual,
                'idempotency_key' => $key,
                'occurred_at' => $now,
            ]);

            $counter = UsageCounter::query()->where('tenant_id', $tenantId)->where('meter', $reservation->meter)->whereDate('period_start', $reservation->period_start->toDateString());
            (clone $counter)->increment('used', $actual);
            (clone $counter)->decrement('reserved', $reservation->quantity);
            $reservation->update(['state' => UsageReservation::SETTLED]);

            Log::info('usage.settled', ['tenant_id' => $tenantId, 'meter' => $reservation->meter, 'reserved' => $reservation->quantity, 'actual' => $actual]);
        });
    }
}
