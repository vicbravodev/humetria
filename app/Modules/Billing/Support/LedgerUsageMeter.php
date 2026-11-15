<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Actions\ReleaseUsage;
use App\Modules\Billing\Actions\ReserveUsage;
use App\Modules\Billing\Actions\SettleUsage;
use App\Modules\Billing\Contracts\UsageMeter;
use App\Modules\Billing\Data\UsageHistoryRow;
use App\Modules\Billing\Data\UsageSnapshot;
use App\Modules\Billing\Models\UsageCounter;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class LedgerUsageMeter implements UsageMeter
{
    public function __construct(private ReserveUsage $reserve, private SettleUsage $settle, private ReleaseUsage $release, private UsageQuota $quota) {}

    public function reserve(string $tenantId, string $meter, int $quantity, string $key): bool
    {
        return $this->reserve->handle($tenantId, $meter, $quantity, $key);
    }

    public function settle(string $tenantId, string $key, int $actual): void
    {
        $this->settle->handle($tenantId, $key, $actual);
    }

    public function release(string $tenantId, string $key): void
    {
        $this->release->handle($tenantId, $key);
    }

    public function snapshot(string $tenantId, string $meter): UsageSnapshot
    {
        $period = UsagePeriod::now();
        $counter = UsageCounter::query()->where('tenant_id', $tenantId)->where('meter', $meter)->whereDate('period_start', $period->toDateString())->first();
        $granted = $this->quota->granted($tenantId, $meter, $period);

        return new UsageSnapshot($meter, $period->toDateString(), UsagePeriod::label($period), $counter->used ?? 0, $counter->reserved ?? 0, $this->quota->base($tenantId, $meter) + $granted, $granted);
    }

    public function history(string $tenantId, string $meter, int $perPage = 6, string $pageName = 'usage_page'): LengthAwarePaginator
    {
        return UsageCounter::query()->where('tenant_id', $tenantId)->where('meter', $meter)->orderByDesc('period_start')
            ->paginate($perPage, pageName: $pageName)->withQueryString()
            ->through(fn (UsageCounter $row): UsageHistoryRow => new UsageHistoryRow($row->period_start->toDateString(), UsagePeriod::label($row->period_start), $row->used, $row->quota));
    }
}
