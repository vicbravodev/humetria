<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Models\UsageCounter;
use App\Modules\Billing\Models\UsageEvent;
use App\Modules\Billing\Support\UsagePeriod;
use App\Modules\Billing\Support\UsageQuota;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Counts a use that already happened (an email accepted for sending): one event per key, then the counter. It measures; it never refuses. */
final readonly class RecordUsage
{
    public function __construct(private UsageQuota $quota) {}

    /**
     * @param  bool  $fromHold  the use takes its place from what a bulk send held (never below zero).
     * @return bool false when the key was counted before.
     */
    public function handle(string $tenantId, string $meter, int $quantity, string $key, bool $fromHold = false): bool
    {
        $now = CarbonImmutable::now();
        $period = UsagePeriod::containing($now);

        // On the connection itself: a mail goes out inside other writers' transactions (and their lock-order tests watch the manager).
        return DB::connection()->transaction(function () use ($tenantId, $meter, $quantity, $key, $now, $period, $fromHold): bool {
            $new = UsageEvent::query()->insertOrIgnore([
                'id' => Str::uuid()->toString(),
                'tenant_id' => $tenantId,
                'meter' => $meter,
                'period_start' => $period->toDateString(),
                'quantity' => $quantity,
                'idempotency_key' => $key,
                'occurred_at' => $now,
            ]);

            if ($new === 0) {
                return false;
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

            $counter = UsageCounter::query()->where('tenant_id', $tenantId)->where('meter', $meter)->whereDate('period_start', $period->toDateString());
            (clone $counter)->increment('used', $quantity, ['quota' => $this->quota->for($tenantId, $meter, $period)]);

            if ($fromHold) {
                // Only what is held: with no hold left the use takes nothing from it (never below zero).
                (clone $counter)->where('reserved', '>=', $quantity)->decrement('reserved', $quantity);
            }

            Log::info('usage.recorded', ['tenant_id' => $tenantId, 'meter' => $meter, 'quantity' => $quantity]);

            return true;
        });
    }
}
