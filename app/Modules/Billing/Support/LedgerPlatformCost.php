<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Contracts\PlatformCost;
use App\Modules\Billing\Contracts\UsageMeter;
use App\Modules\Billing\Data\MessagingMargin;
use App\Modules\Billing\Models\UsageEvent;
use Carbon\CarbonImmutable;

final class LedgerPlatformCost implements PlatformCost
{
    public function whatsAppMonth(CarbonImmutable $month): MessagingMargin
    {
        $totals = UsageEvent::query()->toBase()
            ->where('meter', UsageMeter::WHATSAPP)
            ->whereDate('period_start', UsagePeriod::containing($month)->toDateString())
            ->selectRaw('coalesce(sum(quantity), 0) as messages, coalesce(sum(quantity * unit_cost_micros), 0) as cost, coalesce(sum(quantity * unit_price_micros), 0) as charged')
            ->first();

        return new MessagingMargin(self::whole($totals->messages ?? 0), self::cents($totals->cost ?? 0), self::cents($totals->charged ?? 0));
    }

    /** Micros to cents, to the nearest. */
    private static function cents(mixed $micros): int
    {
        return (int) round(self::whole($micros) / 10_000);
    }

    private static function whole(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
