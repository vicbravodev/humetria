<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Contracts\StorageGuard;
use App\Modules\Billing\Exceptions\StorageQuotaExceeded;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\UsageCounter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final readonly class LedgerStorageGuard implements StorageGuard
{
    private const string METER = 'storage_bytes';

    public function __construct(private UsageQuota $quota) {}

    public function admit(string $path, int $bytes): void
    {
        $tenantId = $this->companyOf($path);

        if ($tenantId === null || ! Subscription::query()->where('tenant_id', $tenantId)->exists()) {
            return;
        }

        $now = CarbonImmutable::now();
        $period = UsagePeriod::containing($now);
        $limit = $this->quota->for($tenantId, self::METER, $period);

        // A month's first file starts from what the last month had: nothing leaves by itself, and the nightly measure corrects the rest.
        UsageCounter::query()->insertOrIgnore([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $tenantId,
            'meter' => self::METER,
            'period_start' => $period->toDateString(),
            'used' => UsageCounter::query()->where('tenant_id', $tenantId)->where('meter', self::METER)->orderByDesc('period_start')->first()->used ?? 0,
            'reserved' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $taken = UsageCounter::query()
            ->where('tenant_id', $tenantId)->where('meter', self::METER)->whereDate('period_start', $period->toDateString())
            ->whereRaw('"used" + ? <= ?', [$bytes, $limit])
            ->increment('used', $bytes, ['quota' => $limit]);

        if ($taken === 0) {
            Log::info('usage.storage_refused', ['tenant_id' => $tenantId, 'bytes' => $bytes, 'quota' => $limit]);

            throw new StorageQuotaExceeded('Tu plan incluye '.$this->size($limit).' de archivos y este no cabe. Puedes seguir leyendo, descargando y borrando los que ya tienes; para subir más, pide una ampliación a Humetria.');
        }
    }

    public function release(string $path, int $bytes): void
    {
        $tenantId = $this->companyOf($path);

        if ($tenantId === null) {
            return;
        }

        UsageCounter::query()
            ->where('tenant_id', $tenantId)->where('meter', self::METER)->whereDate('period_start', UsagePeriod::now()->toDateString())
            ->where('used', '>=', $bytes)
            ->decrement('used', $bytes);
    }

    /** The company of `{type}/{company}/…`, when that is a company id. */
    private function companyOf(string $path): ?string
    {
        $company = explode('/', $path)[1] ?? '';

        return Str::isUuid($company) ? $company : null;
    }

    private function size(int $bytes): string
    {
        return $bytes >= 1_073_741_824
            ? rtrim(rtrim(number_format($bytes / 1_073_741_824, 1, '.', ''), '0'), '.').' GB'
            : max(1, intdiv($bytes, 1_048_576)).' MB';
    }
}
