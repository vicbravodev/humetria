<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Contracts\Timezones;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Str;

/** The zones of the current company and its work centers: the company's scope keeps another's center out. */
final readonly class CompanyTimezones implements Timezones
{
    public function __construct(private CurrentTenant $tenant) {}

    public function forCenter(string $workCenterId): string
    {
        return $this->forCenters([$workCenterId])[$workCenterId];
    }

    public function forCenters(array $workCenterIds): array
    {
        $ids = array_values(array_filter($workCenterIds, Str::isUuid(...)));
        $found = $ids === [] ? [] : WorkCenter::query()->whereIn('id', $ids)->pluck('timezone', 'id')->all();
        $zones = [];

        foreach ($workCenterIds as $id) {
            $zones[$id] = is_string($found[$id] ?? null) ? $found[$id] : $this->forTenant();
        }

        return $zones;
    }

    public function forTenant(): string
    {
        return Tenant::query()->findOrFail($this->tenant->id(), ['id', 'timezone'])->timezone;
    }
}
