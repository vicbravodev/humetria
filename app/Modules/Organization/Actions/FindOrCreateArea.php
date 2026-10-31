<?php

declare(strict_types=1);

namespace App\Modules\Organization\Actions;

use App\Modules\Organization\Models\Area;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\Log;

final readonly class FindOrCreateArea
{
    public function __construct(private CurrentTenant $currentTenant) {}

    /** The company's area with that name, whatever the case, or a new one. */
    public function handle(string $name): Area
    {
        $existing = Area::query()->whereRaw('lower(name) = lower(?)', [$name])->first();

        if ($existing !== null) {
            return $existing;
        }

        $area = Area::query()->create(['tenant_id' => $this->currentTenant->id(), 'name' => $name]);
        Log::info('area.created', ['area_id' => $area->id]);

        return $area;
    }
}
