<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Contracts\Timezones;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * The zones of the current company and its work centers: the company's scope keeps another's center out. One instance per
 * request or job (scoped), so a page that asks for the same zone many times reads it once; the memo is by company, as a
 * nightly run moves from one company to the next.
 */
final class CompanyTimezones implements Timezones
{
    /** @var array<string, array{tenant?: string, centers: array<string, string>}> by company id */
    private array $memo = [];

    public function __construct(private readonly CurrentTenant $tenant) {}

    public function forCenter(string $workCenterId): string
    {
        return $this->forCenters([$workCenterId])[$workCenterId];
    }

    public function forCenters(array $workCenterIds): array
    {
        $company = $this->tenant->id();
        $known = $this->memo[$company]['centers'] ?? [];
        $missing = array_values(array_filter(array_diff($workCenterIds, array_keys($known)), Str::isUuid(...)));

        if ($missing !== []) {
            foreach (WorkCenter::query()->whereIn('id', $missing)->pluck('timezone', 'id')->all() as $id => $zone) {
                if (is_string($id) && is_string($zone)) {
                    $known[$id] = $zone;
                }
            }
            $this->memo[$company]['centers'] = $known;
        }

        $zones = [];
        foreach ($workCenterIds as $id) {
            $zones[$id] = $known[$id] ?? $this->forTenant();
        }

        return $zones;
    }

    public function forTenant(): string
    {
        $company = $this->tenant->id();

        return $this->memo[$company]['tenant'] ??= Tenant::query()->findOrFail($company, ['id', 'timezone'])->timezone;
    }

    public function label(string $timezone): string
    {
        return MexicanTimezones::label($timezone, CarbonImmutable::now());
    }
}
