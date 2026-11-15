<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Models\UsageCounter;
use App\Modules\Billing\Models\UsageReservation;
use App\Modules\Billing\Support\UsagePeriod;
use App\Modules\Billing\Support\UsageQuota;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final readonly class ReserveUsage
{
    public function __construct(private UsageQuota $quota) {}

    /**
     * Holds the quantity for the call under its key, if the month's quota has that much left (used + held + this <= quota), with a single
     * conditional UPDATE on the counter. A key that already has a hold, or was settled, is not held again. Ids and counts only reach the log.
     */
    public function handle(string $tenantId, string $meter, int $quantity, string $key): bool
    {
        $now = CarbonImmutable::now();
        $period = UsagePeriod::containing($now);

        return DB::transaction(function () use ($tenantId, $meter, $quantity, $key, $now, $period): bool {
            $new = UsageReservation::query()->insertOrIgnore([
                'id' => Str::uuid()->toString(),
                'tenant_id' => $tenantId,
                'meter' => $meter,
                'period_start' => $period->toDateString(),
                'idempotency_key' => $key,
                'quantity' => $quantity,
                'state' => UsageReservation::RESERVED,
                'expires_at' => $now->addMinutes(config()->integer('billing.reservation_ttl_minutes')),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($new === 0) {
                $state = UsageReservation::query()->where('tenant_id', $tenantId)->where('idempotency_key', $key)->value('state');

                return in_array($state, [UsageReservation::RESERVED, UsageReservation::SETTLED], true);
            }

            UsageCounter::query()->insertOrIgnore([
                'id' => Str::uuid()->toString(),
                'tenant_id' => $tenantId,
                'meter' => $meter,
                'period_start' => $period->toDateString(),
                'used' => 0,
                'reserved' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $limit = $this->quota->for($tenantId, $meter, $period);
            $taken = UsageCounter::query()
                ->where('tenant_id', $tenantId)->where('meter', $meter)->whereDate('period_start', $period->toDateString())
                ->whereRaw('"used" + "reserved" + ? <= ?', [$quantity, $limit])
                ->increment('reserved', $quantity, ['quota' => $limit]);

            if ($taken === 0) {
                UsageReservation::query()->where('tenant_id', $tenantId)->where('idempotency_key', $key)->delete();
                Log::info('usage.reservation_refused', ['tenant_id' => $tenantId, 'meter' => $meter, 'quantity' => $quantity, 'quota' => $limit]);

                return false;
            }

            Log::info('usage.reserved', ['tenant_id' => $tenantId, 'meter' => $meter, 'quantity' => $quantity]);

            return true;
        });
    }
}
