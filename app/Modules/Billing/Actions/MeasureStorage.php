<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Models\UsageCounter;
use App\Modules\Billing\Models\UsageEvent;
use App\Modules\Billing\Support\UsagePeriod;
use App\Modules\Billing\Support\UsageQuota;
use App\Modules\Tenancy\Contracts\CompanyRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Sums the real size of what each company keeps (paths are `{type}/{company}/…`, so one prefix is one company) and sets the
 * month's figure to it. Everything that takes space counts, even what the screen hides. It measures; it never refuses or deletes.
 * It is also the backfill: no data is migrated. ponytail: lists every object on the disks; use a bucket inventory if that gets slow.
 */
final readonly class MeasureStorage
{
    public const string METER = 'storage_bytes';

    public function __construct(private CompanyRegistry $companies, private UsageQuota $quota) {}

    /** @return int companies measured */
    public function handle(): int
    {
        $known = array_flip($this->companies->ids());
        $bytes = array_fill_keys(array_keys($known), 0);

        foreach (array_unique(array_map(fn (string $key): string => config()->string("filesystems.{$key}"), $this->diskKeys())) as $name) {
            $disk = Storage::disk($name);

            foreach ($disk->directories() as $type) {
                foreach ($disk->directories($type) as $prefix) {
                    $tenantId = basename($prefix);

                    if (isset($known[$tenantId])) {
                        $bytes[$tenantId] += array_sum(array_map($disk->size(...), $disk->allFiles($prefix)));
                    }
                }
            }
        }

        $now = CarbonImmutable::now();
        $period = UsagePeriod::containing($now);

        DB::transaction(function () use ($bytes, $now, $period): void {
            foreach ($bytes as $tenantId => $size) {
                $this->set((string) $tenantId, $size, $now, $period);
            }
        });

        Log::info('usage.storage_measured', ['companies' => count($bytes), 'bytes' => array_sum($bytes)]);

        return count($bytes);
    }

    private function set(string $tenantId, int $size, CarbonImmutable $now, CarbonImmutable $period): void
    {
        UsageEvent::query()->insertOrIgnore([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $tenantId,
            'meter' => self::METER,
            'period_start' => $period->toDateString(),
            'quantity' => $size,
            'idempotency_key' => 'storage:'.$now->setTimezone(config()->string('app.display_timezone'))->toDateString(),
            'occurred_at' => $now,
        ]);

        UsageCounter::query()->upsert(
            [['id' => Str::uuid()->toString(), 'tenant_id' => $tenantId, 'meter' => self::METER, 'period_start' => $period->toDateString(), 'used' => $size, 'reserved' => 0, 'quota' => $this->quota->for($tenantId, self::METER, $period), 'created_at' => $now, 'updated_at' => $now]],
            ['tenant_id', 'meter', 'period_start'],
            ['used', 'quota', 'updated_at'],
        );
    }

    /** @return list<string> */
    private function diskKeys(): array
    {
        return array_values(array_filter(config()->array('billing.storage_disks'), is_string(...)));
    }
}
