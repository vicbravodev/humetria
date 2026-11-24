<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Models\UsageCounter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * The all-or-none hold of a bulk send on a meter's counter of the month: one conditional UPDATE, so of two launches for the last
 * of the quota the second is refused. Shared by the mail and WhatsApp quotas.
 */
final class CounterHold
{
    /** @return int|null null when it held `$count`; otherwise what is left of `$limit` (nothing is held then). */
    public function take(string $tenantId, string $meter, int $count, int $limit): ?int
    {
        $now = CarbonImmutable::now();
        $period = UsagePeriod::containing($now);

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
        $taken = (clone $counter)->whereRaw('"used" + "reserved" + ? <= ?', [$count, $limit])->increment('reserved', $count, ['quota' => $limit]);

        if ($taken > 0) {
            return null;
        }

        $current = (clone $counter)->first();

        return max(0, $limit - ($current->used ?? 0) - ($current->reserved ?? 0));
    }
}
